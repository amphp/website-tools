<?php

namespace Amp\WebsiteTools;

use Symfony\Component\Yaml\Yaml;

final class MarkdownConverter
{
    public static function convert(string $permalink, string $markdown, string $htmlUrl, string $description, string $imagePath): string
    {
        $title = null;

        if (\preg_match('(#(.*)\n)', $markdown, $match)) {
            $title = \trim($match[1]);
            $markdown = \ltrim(\str_replace($match[0], '', $markdown));
        }

        if ($title === null) {
            throw new \Exception('Missing title');
        }

        $markdown = \str_replace('> **Note**', '{:.note}', $markdown);
        $markdown = \str_replace('> **Warning**', '{:.warning}', $markdown);

        $meta = [
            'notice' => 'This file is imported and can be edited at ' . $htmlUrl,
            'title' => $title,
            'description' => $description,
            'image' => $imagePath,
            'permalink' => $permalink,
            'source' => $htmlUrl,
            'layout' => 'docs',
        ];

        return "---\n" . Yaml::dump($meta) . "---\n" . $markdown;
    }
}