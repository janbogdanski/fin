<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Blog;

use App\Shared\Infrastructure\Blog\MarkdownBlogRepository;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Mutation-killing tests for MarkdownBlogRepository.
 *
 * Targets: frontmatter parsing (colonPos, trim, substr), date parsing fallback,
 * keyword filtering, usort order, glob false/empty check, buildFilePath concat.
 */
final class MarkdownBlogRepositoryMutationTest extends TestCase
{
    private string $contentDir;

    private ConverterInterface $converter;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/taxpilot_blog_mutation_' . uniqid();
        mkdir($this->contentDir, 0o777, true);
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $files = glob($this->contentDir . '/*.md');
        if ($files !== false) {
            array_map('unlink', $files);
        }
        @rmdir($this->contentDir);
    }

    /**
     * Kills Coalesce mutant on $frontmatter['date'] ?? '1970-01-01'.
     * Post without date field should fallback to 1970-01-01.
     */
    public function testMissingDateDefaultsTo1970(): void
    {
        $this->createFile('no-date.md', <<<'MD'
---
title: No Date
slug: no-date
description: Test
keywords: test
---
Content
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('no-date');

        self::assertNotNull($post);
        self::assertSame('1970-01-01', $post->date->format('Y-m-d'));
    }

    /**
     * Kills mutation on date parsing: invalid date format should fallback to 1970-01-01.
     */
    public function testInvalidDateFormatFallsBackTo1970(): void
    {
        $this->createFile('bad-date.md', <<<'MD'
---
title: Bad Date
slug: bad-date
description: Test
date: not-a-date
keywords: test
---
Content
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('bad-date');

        self::assertNotNull($post);
        self::assertSame('1970-01-01', $post->date->format('Y-m-d'));
    }

    /**
     * Kills mutation on keyword filtering: empty keywords after explode should be filtered out.
     */
    public function testEmptyKeywordsFieldProducesEmptyArray(): void
    {
        $this->createFile('no-keywords.md', <<<'MD'
---
title: No Keywords
slug: no-keywords
description: Test
date: 2026-01-01
keywords:
---
Content
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('no-keywords');

        self::assertNotNull($post);
        self::assertSame([], $post->keywords);
    }

    /**
     * Kills mutation on keyword trimming: keywords with extra whitespace should be trimmed.
     */
    public function testKeywordsAreTrimmed(): void
    {
        $this->createFile('trimmed-kw.md', <<<'MD'
---
title: Trimmed
slug: trimmed-kw
description: Test
date: 2026-01-01
keywords:  tax ,  pit , crypto
---
Content
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('trimmed-kw');

        self::assertNotNull($post);
        self::assertSame(['tax', 'pit', 'crypto'], $post->keywords);
    }

    /**
     * Kills mutation on frontmatter split: content without frontmatter
     * should return full content as body, empty meta.
     */
    public function testContentWithoutFrontmatterUsesDefaults(): void
    {
        $this->createFile('no-frontmatter.md', "Just plain content\n\nWith paragraphs.");

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('no-frontmatter');

        self::assertNotNull($post);
        self::assertSame('', $post->title);
        self::assertSame('', $post->slug);
        self::assertStringContainsString('plain content', $post->htmlContent);
    }

    /**
     * Kills mutations on frontmatter colon position: lines without colon should be skipped.
     */
    public function testFrontmatterLineWithoutColonIsSkipped(): void
    {
        $this->createFile('bad-line.md', <<<'MD'
---
title: Good Line
this line has no colon
slug: bad-line
description: Test
date: 2026-01-01
keywords: test
---
Content
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('bad-line');

        self::assertNotNull($post);
        self::assertSame('Good Line', $post->title);
        self::assertSame('bad-line', $post->slug);
    }

    /**
     * Kills Coalesce mutations on title, slug, description: all default to ''.
     */
    public function testMissingFrontmatterFieldsDefaultToEmptyString(): void
    {
        $this->createFile('minimal.md', <<<'MD'
---
date: 2026-01-01
---
Content
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('minimal');

        self::assertNotNull($post);
        self::assertSame('', $post->title);
        self::assertSame('', $post->slug);
        self::assertSame('', $post->description);
    }

    /**
     * Kills usort order mutation: $b->date <=> $a->date (DESC).
     * If mutated to ASC, order would reverse.
     */
    public function testFindAllSortsNewestFirst(): void
    {
        $this->createFile('old.md', <<<'MD'
---
title: Old
slug: old
description: Old
date: 2025-01-01
keywords: test
---
Old
MD);

        $this->createFile('new.md', <<<'MD'
---
title: New
slug: new
description: New
date: 2026-06-01
keywords: test
---
New
MD);

        $this->createFile('mid.md', <<<'MD'
---
title: Mid
slug: mid
description: Mid
date: 2025-06-01
keywords: test
---
Mid
MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $posts = $repo->findAll();

        self::assertCount(3, $posts);
        self::assertSame('new', $posts[0]->slug);
        self::assertSame('mid', $posts[1]->slug);
        self::assertSame('old', $posts[2]->slug);
    }

    /**
     * Kills ArrayOneItem mutant on findAll: must return ALL posts, not just first.
     */
    public function testFindAllReturnsAllPosts(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createFile("post-{$i}.md", <<<MD
---
title: Post {$i}
slug: post-{$i}
description: Description {$i}
date: 2026-0{$i}-01
keywords: test
---
Content {$i}
MD);
        }

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $posts = $repo->findAll();

        self::assertCount(3, $posts);
    }

    private function createFile(string $filename, string $content): void
    {
        file_put_contents($this->contentDir . '/' . $filename, $content);
    }
}
