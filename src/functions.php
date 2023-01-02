<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\WebsiteTools\GitHubClient;
use Amp\WebsiteTools\MarkdownConverter;
use function Emoji\detect_emoji;

function syncReadme(GitHubClient $gitHubClient, string $source, string $description, string $imagePath): void
{
    $repository = explode('@', $source)[0];
    $reference = explode('@', $source)[1];

    $permalink = substr($repository, strpos($repository, '/'));

    [$content, $htmlUrl] = $gitHubClient->getReadme($repository, '/', $reference);

    if ($content === null) {
        throw new Exception("Reference '$reference' not found in '$repository' repository");
    }

    $docs = MarkdownConverter::convert(
        $permalink,
        $content,
        $htmlUrl,
        $description,
        $imagePath
    );

    $filePath = $permalink . '.md';

    try {
        $file = $gitHubClient->get('https://api.github.com/repos/amphp/amphp.org/contents' . $filePath . '?ref=main');
        $fileSha = $file['sha'];

        if (base64_decode($file["content"]) === $docs) {
            print 'Skipping update of ' . $filePath . ', already up-to-date' . PHP_EOL;
        } else {
            print 'Updating ' . $filePath . PHP_EOL;
            $gitHubClient->updateFile('amphp/amphp.org', 'main', $fileSha, $filePath, $docs, 'Sync ' . ltrim($filePath, '/'), 'contact@amphp.org', 'AMPHP Bot');
        }
    } catch (Exception) {
        print 'Creating ' . $filePath . PHP_EOL;
        $gitHubClient->createFile('amphp/amphp.org', 'main', $filePath, $docs, 'Sync ' . ltrim($filePath, '/'), 'contact@amphp.org', 'AMPHP Bot');
    }
}

/**
 * Escape emoji for jekyll.
 */
function replaceEmojis(string $content): string
{
    while ($emoji = detect_emoji($content)) {
        $emoji = $emoji[0];

        $replacement = '&#x' . implode(';&#x', $emoji['points_hex']) . ';';

        $content = substr($content, 0, $emoji['byte_offset'])
            . $replacement
            . substr($content, $emoji['byte_offset'] + strlen($emoji['emoji']));
    }

    return $content;
}

function syncReleases(GitHubClient $gitHubClient): void
{
    $repositories = $gitHubClient->getRepositories('amphp');
    $latestReleases = [];

    $httpClient = HttpClientBuilder::buildDefault();

    foreach ($repositories as $repository) {
        $repositoryName = $repository["full_name"];

        if (in_array($repositoryName, [
            'amphp/windows-process-wrapper',
            'amphp/uri',
            'amphp/thread'
        ], true)) {
            continue;
        }

        try {
            $latestRelease = $gitHubClient->getLatestRelease($repositoryName);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Not Found')) {
                continue;
            }

            throw $e;
        }

        $composerUrl = "https://raw.githubusercontent.com/$repositoryName/" . $latestRelease['tag_name'] . "/composer.json";
        $composerBody = $httpClient->request(new Request($composerUrl))->getBody()->buffer();

        $v3 = str_contains($composerBody, 'revolt/event-loop')
            || str_contains($composerBody, 'amphp/amp": "^3')
            || str_contains($composerBody, 'amphp/byte-stream": "^2')
            || str_contains($composerBody, 'amphp/process": "^2')
            || str_contains($composerBody, 'amphp/socket": "^2')
            || str_contains($composerBody, 'amphp/http-server": "^3');

        $latestReleases[] = [
            'name' => $repositoryName . ' ' . $latestRelease['name'],
            'package' => $repositoryName,
            'tag_name' => $latestRelease['tag_name'],
            'html_url' => $latestRelease['html_url'],
            'date' => $latestRelease['published_at'],
            'body' => replaceEmojis($latestRelease['body']),
            'revolt' => $v3,
        ];
    }

    usort($latestReleases, static fn ($a, $b) => DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $b['date']) <=> DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $a['date']));

    $filePath = '/_data/releases.json';
    $json = json_encode($latestReleases);

    try {
        $file = $gitHubClient->get('https://api.github.com/repos/amphp/amphp.org/contents' . $filePath . '?ref=main');
        $fileSha = $file['sha'];

        if (base64_decode($file["content"]) === $json) {
            print 'Skipping update of ' . $filePath . ', already up-to-date' . PHP_EOL;
        } else {
            print 'Updating ' . $filePath . PHP_EOL;
            $gitHubClient->updateFile('amphp/amphp.org', 'main', $fileSha, $filePath, $json, 'Sync ' . ltrim($filePath, '/'), 'contact@amphp.org', 'AMPHP Bot');
        }
    } catch (Exception) {
        print 'Creating ' . $filePath . PHP_EOL;
        $gitHubClient->createFile('amphp/amphp.org', 'main', $filePath, $json, 'Sync ' . ltrim($filePath, '/'), 'contact@amphp.org', 'AMPHP Bot');
    }
}
