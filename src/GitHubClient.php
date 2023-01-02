<?php

namespace Amp\WebsiteTools;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use function Kelunik\LinkHeaderRfc5988\parseLinks;

final class GitHubClient
{
    private string $accessToken;
    private HttpClient $httpClient;

    public function __construct(string $accessToken, ?HttpClient $httpClient = null)
    {
        $this->accessToken = $accessToken;
        $this->httpClient = $httpClient ?? HttpClientBuilder::buildDefault();
    }

    public function getHead(string $repository, string $branch): string
    {
        $json = $this->get("https://api.github.com/repos/{$repository}/git/refs/heads/{$branch}");

        return $json["object"]["sha"];
    }

    public function get(string $uri): mixed
    {
        $request = new Request($uri);
        $request->setHeader("authorization", "token {$this->accessToken}");

        $response = $this->httpClient->request(
            $request
        );

        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        if ($response->getStatus() !== 200) {
            throw new \Exception("Request failed ($uri): " . $json["message"] . " (" . $json["documentation_url"] . ")");
        }

        return $json;
    }

    public function getReadme(string $repository, string $path = '/', ?string $ref = null): ?array
    {
        try {
            $uri = "https://api.github.com/repos/{$repository}/readme/{$path}";

            if ($ref !== null) {
                $uri .= '?ref=' . \urlencode($ref);
            }

            $json = $this->get($uri);

            return [\base64_decode($json["content"]), $json['html_url']];
        } catch (\Exception) {
            return null;
        }
    }

    public function getSubmoduleVersion(string $repository, string $path): ?string
    {
        try {
            $json = $this->get("https://api.github.com/repos/{$repository}/contents/{$path}");

            return $json["sha"];
        } catch (\Exception) {
            return null;
        }
    }

    public function getLatestRelease(string $repository)
    {
        return $this->get("https://api.github.com/repos/{$repository}/releases/latest");
    }

    public function getCommitTree($repository, $sha)
    {
        $json = $this->get("https://api.github.com/repos/{$repository}/git/commits/{$sha}");

        return $json["tree"]["sha"];
    }

    public function getTree($repository, $baseTree)
    {
        $json = $this->get("https://api.github.com/repos/{$repository}/git/trees/{$baseTree}");

        if ($json["truncated"] === true) {
            throw new \Exception("Got a truncated tree: {$json["sha"]}");
        }

        return $json;
    }

    public function createTree($repository, $baseTree, $tree)
    {
        $json = $this->post("https://api.github.com/repos/{$repository}/git/trees", [
            "base_tree" => $baseTree,
            "tree" => $tree,
        ]);

        return $json;
    }

    public function post(string $uri, array $json): mixed
    {
        $body = json_encode($json);

        $request = new Request($uri, 'POST');
        $request->setHeader("authorization", "token {$this->accessToken}");
        $request->setBody($body);

        $response = $this->httpClient->request($request);

        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        if ((int)($response->getStatus() / 100) !== 2) {
            throw new \Exception("Request failed (" . $response->getStatus() . "): " . $body);
        }

        return $json;
    }

    public function createCommit(string $repository, string $message, string $tree, array $parents)
    {
        $json = $this->post("https://api.github.com/repos/{$repository}/git/commits", [
            "message" => $message,
            "tree" => $tree,
            "parents" => $parents,
        ]);

        return $json;
    }

    public function updateHead($repository, $branch, $sha)
    {
        $json = $this->patch("https://api.github.com/repos/{$repository}/git/refs/heads/{$branch}", [
            "sha" => $sha,
            "force" => false,
        ]);

        return $json;
    }

    public function patch(string $uri, array $json): mixed
    {
        $body = json_encode($json);

        $request = new Request($uri, 'PATCH');
        $request->setHeader("authorization", "token {$this->accessToken}");
        $request->setBody($body);

        $response = $this->httpClient->request($request);

        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        if ((int)($response->getStatus() / 100) !== 2) {
            throw new \Exception("Request failed (" . $response->getStatus() . "): " . $body);
        }

        return $json;
    }

    public function put(string $uri, array $json): mixed
    {
        $body = json_encode($json);

        $request = new Request($uri, 'PUT');
        $request->setHeader("authorization", "token {$this->accessToken}");
        $request->setBody($body);

        $response = $this->httpClient->request($request);

        $body = $response->getBody()->buffer();
        $json = \json_decode($body, true);

        if ((int)($response->getStatus() / 100) !== 2) {
            throw new \Exception("Request failed (" . $response->getStatus() . "): " . $body);
        }

        return $json;
    }

    public function getRepositories(string $organization): iterable
    {
        $uri = "https://api.github.com/orgs/{$organization}/repos";

        do {
            $request = new Request($uri);
            $request->setHeader("authorization", "token {$this->accessToken}");

            $response = $this->httpClient->request($request);

            $json = \json_decode($response->getBody()->buffer(), true);

            if ($response->getStatus() !== 200) {
                throw new \Exception("Request failed ($uri): " . $json["message"] . " (" . $json["documentation_url"] . ")");
            }

            foreach ($json as $repository) {
                yield $repository;
            }

            $linkHeader = $response->getHeader("link");
            $links = parseLinks($linkHeader ?? "");

            $uri = ($link = $links->getByRel("next")) ? $link->getUri() : null;
        } while ($uri);
    }

    public function createFile(string $repository, string $branch, string $filePath, string $content, string $commitMessage, string $committerEmail, string $committerName)
    {
        $json = $this->put("https://api.github.com/repos/{$repository}/contents" . $filePath, [
            'branch' => $branch,
            "message" => $commitMessage,
            "committer" => [
                'name' => $committerName,
                'email' => $committerEmail,
            ],
            'content' => \base64_encode($content),
        ]);

        return $json;
    }

    public function updateFile(string $repository, string $branch, string $sha, string $filePath, string $content, string $commitMessage, string $committerEmail, string $committerName)
    {
        $json = $this->put("https://api.github.com/repos/{$repository}/contents" . $filePath, [
            'branch' => $branch,
            'sha' => $sha,
            "message" => $commitMessage,
            "committer" => [
                'name' => $committerName,
                'email' => $committerEmail,
            ],
            'content' => \base64_encode($content),
        ]);

        return $json;
    }
}