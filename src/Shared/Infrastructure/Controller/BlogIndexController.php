<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Shared\Infrastructure\Blog\MarkdownBlogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogIndexController extends AbstractController
{
    public function __construct(
        private readonly MarkdownBlogRepository $blogRepository,
    ) {
    }

    #[Route('/blog', name: 'blog_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('blog/index.html.twig', [
            'posts' => $this->blogRepository->findAll(),
        ]);
    }
}
