<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Blog;

use App\Shared\Infrastructure\Blog\BlogPost;
use App\Shared\Infrastructure\Blog\MarkdownBlogRepository;
use PHPUnit\Framework\TestCase;

final class MarkdownBlogRepositoryTest extends TestCase
{
    private string $contentDir;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/taxpilot_blog_test_' . uniqid();
        mkdir($this->contentDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->contentDir . '/*.md');
        if ($files !== false) {
            array_map('unlink', $files);
        }
        rmdir($this->contentDir);
    }

    public function testFindAllReturnsEmptyArrayWhenNoFiles(): void
    {
        $repo = new MarkdownBlogRepository($this->contentDir);

        self::assertSame([], $repo->findAll());
    }

    public function testFindAllReturnsPostsSortedByDateDescending(): void
    {
        $this->createMarkdownFile('first.md', <<<'MD'
            ---
            title: First Post
            slug: first-post
            description: First description
            date: 2026-01-15
            keywords: tax, pit
            schema_type: Article
            ---
            Hello **world**
            MD);

        $this->createMarkdownFile('second.md', <<<'MD'
            ---
            title: Second Post
            slug: second-post
            description: Second description
            date: 2026-03-01
            keywords: crypto
            schema_type: Article
            ---
            Second content
            MD);

        $repo = new MarkdownBlogRepository($this->contentDir);
        $posts = $repo->findAll();

        self::assertCount(2, $posts);
        self::assertSame('second-post', $posts[0]->slug);
        self::assertSame('first-post', $posts[1]->slug);
    }

    public function testFindBySlugReturnsPostWithRenderedHtml(): void
    {
        $this->createMarkdownFile('test.md', <<<'MD'
            ---
            title: Test Post
            slug: test-post
            description: Test description
            date: 2026-02-10
            keywords: pit-38, rozliczenie
            schema_type: BlogPosting
            ---
            Hello **bold** and *italic*
            MD);

        $repo = new MarkdownBlogRepository($this->contentDir);
        $post = $repo->findBySlug('test-post');

        self::assertNotNull($post);
        self::assertInstanceOf(BlogPost::class, $post);
        self::assertSame('Test Post', $post->title);
        self::assertSame('test-post', $post->slug);
        self::assertSame('Test description', $post->description);
        self::assertSame('2026-02-10', $post->date->format('Y-m-d'));
        self::assertSame(['pit-38', 'rozliczenie'], $post->keywords);
        self::assertSame('BlogPosting', $post->schemaType);
        self::assertStringContainsString('<strong>bold</strong>', $post->htmlContent);
        self::assertStringContainsString('<em>italic</em>', $post->htmlContent);
    }

    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $repo = new MarkdownBlogRepository($this->contentDir);

        self::assertNull($repo->findBySlug('nonexistent'));
    }

    public function testFindBySlugIgnoresFilesWithoutMatchingSlug(): void
    {
        $this->createMarkdownFile('other.md', <<<'MD'
            ---
            title: Other Post
            slug: other-post
            description: Other
            date: 2026-01-01
            keywords: other
            schema_type: Article
            ---
            Content
            MD);

        $repo = new MarkdownBlogRepository($this->contentDir);

        self::assertNull($repo->findBySlug('missing-slug'));
    }

    private function createMarkdownFile(string $filename, string $content): void
    {
        file_put_contents(
            $this->contentDir . '/' . $filename,
            str_replace("\n            ", "\n", ltrim($content)),
        );
    }
}
