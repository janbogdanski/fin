<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Shared\Infrastructure\Blog\MarkdownBlogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BlogPostController extends AbstractController
{
    public function __construct(
        private readonly MarkdownBlogRepository $blogRepository,
    ) {
    }

    #[Route('/blog/{slug}', name: 'blog_post', methods: ['GET'], requirements: [
        'slug' => '[a-z0-9\-]+',
    ])]
    public function __invoke(string $slug): Response
    {
        $post = $this->blogRepository->findBySlug($slug);
        if ($post === null) {
            throw new NotFoundHttpException(sprintf('Blog post "%s" not found.', $slug));
        }

        return $this->render('blog/post.html.twig', [
            'post' => $post,
        ]);
    }
}
