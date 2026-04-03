<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Controller;

use App\Shared\Infrastructure\Blog\MarkdownBlogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    public function __construct(
        private readonly MarkdownBlogRepository $blogRepository,
    ) {
    }

    #[Route('/sitemap.xml', name: 'sitemap_index', methods: ['GET'])]
    public function index(): Response
    {
        $urls = $this->buildUrls();

        $xml = $this->renderView('sitemap/index.xml.twig', [
            'urls' => $urls,
        ]);

        $response = new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
        $response->setSharedMaxAge(86400);
        $response->headers->set('Cache-Control', 'public, max-age=86400, s-maxage=86400');

        return $response;
    }

    /**
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    private function buildUrls(): array
    {
        $urls = [
            [
                'loc' => $this->generateUrl('landing_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => $this->generateUrl('pricing_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            [
                'loc' => $this->generateUrl('blog_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
        ];

        foreach ($this->blogRepository->findAll() as $post) {
            $urls[] = [
                'loc' => $this->generateUrl('blog_post', [
                    'slug' => $post->slug,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        return $urls;
    }
}
