<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Infrastructure\Blog;

use App\Shared\Infrastructure\Blog\BlogPost;
use App\Shared\Infrastructure\Blog\MarkdownBlogRepository;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\ConverterInterface;
use PHPUnit\Framework\TestCase;

final class MarkdownBlogRepositoryTest extends TestCase
{
    private string $contentDir;

    private ConverterInterface $converter;

    protected function setUp(): void
    {
        $this->contentDir = sys_get_temp_dir() . '/taxpilot_blog_test_' . uniqid();
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
        rmdir($this->contentDir);
    }

    public function testFindAllReturnsEmptyArrayWhenNoFiles(): void
    {
        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);

        self::assertSame([], $repo->findAll());
    }

    public function testFindAllReturnsPostsSortedByDateDescending(): void
    {
        $this->createMarkdownFile('first-post.md', <<<'MD'
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

        $this->createMarkdownFile('second-post.md', <<<'MD'
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

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $posts = $repo->findAll();

        self::assertCount(2, $posts);
        self::assertSame('second-post', $posts[0]->slug);
        self::assertSame('first-post', $posts[1]->slug);
    }

    public function testFindBySlugReturnsPostWithRenderedHtml(): void
    {
        $this->createMarkdownFile('test-post.md', <<<'MD'
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

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
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
        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);

        self::assertNull($repo->findBySlug('nonexistent'));
    }

    public function testFindBySlugUsesFilenameConvention(): void
    {
        $this->createMarkdownFile('my-slug.md', <<<'MD'
            ---
            title: Slug Post
            slug: my-slug
            description: Slug test
            date: 2026-01-01
            keywords: test
            schema_type: Article
            ---
            Content
            MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);

        self::assertNotNull($repo->findBySlug('my-slug'));
        self::assertNull($repo->findBySlug('slug-post'));
    }

    public function testInvalidSchemaTypeDefaultsToArticle(): void
    {
        $this->createMarkdownFile('bad-schema.md', <<<'MD'
            ---
            title: Bad Schema
            slug: bad-schema
            description: Invalid schema type
            date: 2026-01-01
            keywords: test
            schema_type: MaliciousType
            ---
            Content
            MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('bad-schema');

        self::assertNotNull($post);
        self::assertSame('Article', $post->schemaType);
    }

    public function testMissingSchemaTypeDefaultsToArticle(): void
    {
        $this->createMarkdownFile('no-schema.md', <<<'MD'
            ---
            title: No Schema
            slug: no-schema
            description: Missing schema type
            date: 2026-01-01
            keywords: test
            ---
            Content
            MD);

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);
        $post = $repo->findBySlug('no-schema');

        self::assertNotNull($post);
        self::assertSame('Article', $post->schemaType);
    }

    public function testAllowedSchemaTypesArePreserved(): void
    {
        foreach (['Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'HowTo'] as $type) {
            $slug = 'schema-' . strtolower($type);
            $this->createMarkdownFile($slug . '.md', <<<MD
                ---
                title: Schema Test
                slug: {$slug}
                description: Testing {$type}
                date: 2026-01-01
                keywords: test
                schema_type: {$type}
                ---
                Content
                MD);
        }

        $repo = new MarkdownBlogRepository($this->contentDir, $this->converter);

        foreach (['Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'HowTo'] as $type) {
            $slug = 'schema-' . strtolower($type);
            $post = $repo->findBySlug($slug);
            self::assertNotNull($post, "Post for schema type {$type} not found");
            self::assertSame($type, $post->schemaType, "Schema type {$type} was not preserved");
        }
    }

    private function createMarkdownFile(string $filename, string $content): void
    {
        file_put_contents(
            $this->contentDir . '/' . $filename,
            str_replace("\n            ", "\n", ltrim($content)),
        );
    }
}
