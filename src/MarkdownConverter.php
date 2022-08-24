<?php

namespace Amp\WebsiteTools;

use Symfony\Component\Yaml\Yaml;

final class MarkdownConverter
{
    public static function convert(string $repository, string $permalink, string $markdown, string $description, string $imagePath): string
    {
        $title = null;

        if (\preg_match('(#(.*)\n)', $markdown, $match)) {
            $title = \trim($match[1]);
            $markdown = \ltrim(\str_replace($match[0], '', $markdown));
        }

        if ($title === null) {
            throw new \Exception('Missing title');
        }

        $meta = [
            'title' => $title,
            'description' => $description,
            'image' => $imagePath,
            'permalink' => $permalink,
            'layout' => 'docs',
        ];

        return "---\n" . Yaml::dump($meta) . "---\n" . $markdown;
    }
}