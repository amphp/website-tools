<?php

use Amp\WebsiteTools\GitHubClient;
use Amp\WebsiteTools\MarkdownConverter;

function syncReadme(GitHubClient $gitHubClient, string $source, string $description, string $imagePath): void
{
    $repository = \explode('@', $source)[0];
    $reference = \explode('@', $source)[1];

    $permalink = \substr($repository, \strpos($repository, '/'));

    [$content, $htmlUrl] = $gitHubClient->getReadme($repository, '/', $reference);

    if ($content === null) {
        throw new \Exception("Reference '$reference' not found in '$repository' repository");
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
        $file = $gitHubClient->get('https://api.github.com/repos/amphp/v3.amphp.org/contents' . $filePath . '?ref=main');
        $fileSha = $file['sha'];

        if (\base64_decode($file["content"]) === $docs) {
            print 'Skipping update of ' . $filePath . ', already up-to-date' . PHP_EOL;
        } else {
            print 'Updating ' . $filePath . PHP_EOL;
            $gitHubClient->updateFile('amphp/v3.amphp.org', 'main', $fileSha, $filePath, $docs, 'Sync ' . ltrim($filePath, '/'), 'contact@amphp.org', 'AMPHP Bot');
        }
    } catch (\Exception) {
        print 'Creating ' . $filePath . PHP_EOL;
        $gitHubClient->createFile('amphp/v3.amphp.org', 'main', $filePath, $docs, 'Sync ' . ltrim($filePath, '/'), 'contact@amphp.org', 'AMPHP Bot');
    }
}
