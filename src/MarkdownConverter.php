<?php

namespace Amp\WebsiteTools;

use Symfony\Component\Yaml\Yaml;

final class MarkdownConverter
{
    public static function convert(string $permalink, string $markdown, string $htmlUrl, string $title, string $description, string $imagePath): string
    {
        if (\preg_match('(#(.*)\n)', $markdown, $match)) {
            $markdown = \ltrim(\str_replace($match[0], '', $markdown));
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