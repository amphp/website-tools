<?php

namespace Amp\WebsiteTools;

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Iterator;
use Amp\Producer;
use Amp\Promise;
use function Amp\call;
use function Kelunik\LinkHeaderRfc5988\parseLinks;

class GitHubClient {
    private $accessToken;
    private $client;

    public function __construct(string $accessToken, Client $client = null) {
        $this->accessToken = $accessToken;
        $this->client = $client ?? new DefaultClient;
    }

    public function get(string $uri): Promise {
        return call(function () use ($uri) {
            /** @var Response $response */
            $response = yield $this->client->request(
                (new Request($uri))->withHeader("authorization", "token {$this->accessToken}")
            );

            $json = \json_decode(yield $response->getBody(), true);

            if ($response->getStatus() !== 200) {
                throw new \Exception("Request failed ($uri): " . $json["message"] . " (" . $json["documentation_url"] . ")");
            }

            return $json;
        });
    }

    public function post(string $uri, array $json): Promise {
        return call(function () use ($uri, $json) {
            $body = json_encode($json);

            /** @var Response $response */
            $response = yield $this->client->request(
                (new Request($uri, "POST"))->withHeader("authorization", "token {$this->accessToken}")
                    ->withBody($body)
            );

            $json = \json_decode(yield $response->getBody(), true);

            if ((int) ($response->getStatus() / 100) !== 2) {
                throw new \Exception("Request failed (" . $response->getStatus() . "): " . yield $response->getBody());
            }

            return $json;
        });
    }

    public function patch(string $uri, array $json): Promise {
        return call(function () use ($uri, $json) {
            $body = json_encode($json);

            /** @var Response $response */
            $response = yield $this->client->request(
                (new Request($uri, "PATCH"))->withHeader("authorization", "token {$this->accessToken}")
                    ->withBody($body)
            );

            $json = \json_decode(yield $response->getBody(), true);

            if ((int) ($response->getStatus() / 100) !== 2) {
                throw new \Exception("Request failed (" . $response->getStatus() . "): " . yield $response->getBody());
            }

            return $json;
        });
    }

    public function getHead(string $repository, string $branch): Promise {
        return call(function () use ($repository, $branch) {
            $json = yield $this->get("https://api.github.com/repos/{$repository}/git/refs/heads/{$branch}");

            return $json["object"]["sha"];
        });
    }

    public function getSubmoduleVersion(string $repository, string $path): Promise {
        return call(function () use ($repository, $path) {
            try {
                $json = yield $this->get("https://api.github.com/repos/{$repository}/contents/{$path}");

                return $json["sha"];
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    public function getCommitTree($repository, $sha) {
        return call(function () use ($repository, $sha) {
            $json = yield $this->get("https://api.github.com/repos/{$repository}/git/commits/{$sha}");

            return $json["tree"]["sha"];
        });
    }

    public function getTree($repository, $baseTree) {
        return call(function () use ($repository, $baseTree) {
            $json = yield $this->get("https://api.github.com/repos/{$repository}/git/trees/{$baseTree}");

            if ($json["truncated"] === true) {
                throw new \Exception("Got a truncated tree: {$json["sha"]}");
            }

            return $json;
        });
    }

    public function createTree($repository, $baseTree, $tree) {
        return call(function () use ($repository, $baseTree, $tree) {
            $json = yield $this->post("https://api.github.com/repos/{$repository}/git/trees", [
                "base_tree" => $baseTree,
                "tree" => $tree,
            ]);

            return $json;
        });
    }

    public function createCommit(string $repository, string $message, string $tree, array $parents) {
        return call(function () use ($repository, $message, $tree, $parents) {
            $json = yield $this->post("https://api.github.com/repos/{$repository}/git/commits", [
                "message" => $message,
                "tree" => $tree,
                "parents" => $parents,
            ]);

            return $json;
        });
    }

    public function updateHead($repository, $branch, $sha) {
        return call(function () use ($repository, $branch, $sha) {
            $json = yield $this->patch("https://api.github.com/repos/{$repository}/git/refs/heads/{$branch}", [
                "sha" => $sha,
                "force" => false,
            ]);

            return $json;
        });
    }

    public function getRepositories(string $organization): Iterator {
        return new Producer(function ($emit) use ($organization) {
            $uri = "https://api.github.com/orgs/{$organization}/repos";

            do {
                /** @var Response $response */
                $response = yield $this->client->request(
                    (new Request($uri))->withHeader("authorization", "token {$this->accessToken}")
                );

                $json = \json_decode(yield $response->getBody(), true);

                if ($response->getStatus() !== 200) {
                    throw new \Exception("Request failed ($uri): " . $json["message"] . " (" . $json["documentation_url"] . ")");
                }

                foreach ($json as $repository) {
                    yield $emit($repository);
                }

                $linkHeader = $response->getHeader("link");
                $links = parseLinks($linkHeader ?? "");

                $uri = ($link = $links->getByRel("next")) ? $link->getUri() : null;
            } while ($uri);
        });
    }
}