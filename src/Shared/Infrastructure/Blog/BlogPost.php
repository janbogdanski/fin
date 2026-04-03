<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Blog;

final readonly class BlogPost
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $description,
        public \DateTimeImmutable $date,
        public array $keywords,
        public string $schemaType,
        public string $htmlContent,
    ) {
    }
}
