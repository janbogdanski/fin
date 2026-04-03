<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Blog;

use League\CommonMark\CommonMarkConverter;

final class MarkdownBlogRepository
{
    private readonly CommonMarkConverter $converter;

    public function __construct(
        private readonly string $contentDir,
    ) {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return list<BlogPost>
     */
    public function findAll(): array
    {
        $files = glob($this->contentDir . '/*.md');
        if ($files === false || $files === []) {
            return [];
        }

        $posts = array_map(fn (string $file): BlogPost => $this->parseFile($file), $files);

        usort($posts, static fn (BlogPost $a, BlogPost $b): int => $b->date <=> $a->date);

        return $posts;
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        $files = glob($this->contentDir . '/*.md');
        if ($files === false || $files === []) {
            return null;
        }

        foreach ($files as $file) {
            $post = $this->parseFile($file);
            if ($post->slug === $slug) {
                return $post;
            }
        }

        return null;
    }

    private function parseFile(string $filePath): BlogPost
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException(sprintf('Cannot read blog file: %s', $filePath));
        }

        [$frontmatter, $body] = $this->splitFrontmatter($raw);

        $html = $this->converter->convert($body)->getContent();
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $frontmatter['date'] ?? '1970-01-01');
        if ($date === false) {
            $date = new \DateTimeImmutable('1970-01-01');
        }

        $keywords = array_map(
            'trim',
            explode(',', $frontmatter['keywords'] ?? ''),
        );
        $keywords = array_values(array_filter($keywords, static fn (string $k): bool => $k !== ''));

        return new BlogPost(
            title: $frontmatter['title'] ?? '',
            slug: $frontmatter['slug'] ?? '',
            description: $frontmatter['description'] ?? '',
            date: $date,
            keywords: $keywords,
            schemaType: $frontmatter['schema_type'] ?? 'Article',
            htmlContent: $html,
        );
    }

    /**
     * @return array{array<string, string>, string}
     */
    private function splitFrontmatter(string $content): array
    {
        if (! str_starts_with(trim($content), '---')) {
            return [[], $content];
        }

        $parts = preg_split('/^---\s*$/m', $content, 3);
        if ($parts === false || count($parts) < 3) {
            return [[], $content];
        }

        $frontmatterRaw = trim($parts[1]);
        $body = trim($parts[2]);

        $meta = [];
        foreach (explode("\n", $frontmatterRaw) as $line) {
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));
            $meta[$key] = $value;
        }

        return [$meta, $body];
    }
}
