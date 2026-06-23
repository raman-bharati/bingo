<?php

namespace App\Controllers;

class Portfolio extends BaseController
{
    public function index(): string
    {
        $portfolio = [
            'site' => [
                'name' => 'Raman Bharati',
                'role' => 'PHP / CodeIgniter Developer',
                'tagline' => 'I build practical web products, real-time game flows, and clean PHP backends.',
                'location' => 'Kathmandu, Nepal',
                'email' => 'hello@ramanbharati.com.np',
                'resumeUrl' => '#',
                'githubUrl' => 'https://github.com/ramanbharati',
                'linkedinUrl' => 'https://www.linkedin.com/in/ramanbharati/',
                'gameUrl' => '/bingo',
            ],
            'about' => 'I am a PHP and CodeIgniter developer from Nepal, focused on shipping useful products with a strong balance of backend structure and simple, effective interfaces. This portfolio highlights the kind of work I build: multiplayer game logic, API-driven features, and straightforward deployable web apps.',
            'skills' => [
                'PHP / CodeIgniter 4',
                'JavaScript / Client-side state handling',
                'REST APIs',
                'MySQL',
                'Real-time game workflows',
                'Deployment and hosting setup',
            ],
            'projects' => [
                [
                    'title' => 'Bingo Multiplayer Game',
                    'summary' => 'A real-time bingo room experience with room management, live state updates, player controls, and a mobile-friendly interface.',
                    'link' => '/bingo',
                    'linkLabel' => 'Play the game',
                ],
                [
                    'title' => 'Api Testing',
                    'summary' => 'A CodeIgniter API project for structured data handling, clean endpoints, and backend workflows that are easy to extend.',
                    'link' => '#',
                    'linkLabel' => 'Add live link',
                ],
                [
                    'title' => 'eSewa Integration Work',
                    'summary' => 'Payment-flow implementation work focused on checkout, callbacks, and keeping the user journey simple and reliable.',
                    'link' => '#',
                    'linkLabel' => 'Add details',
                ],
            ],
            'experience' => [
                [
                    'period' => 'Current',
                    'title' => 'Independent Web Developer',
                    'description' => 'Building and maintaining PHP applications, including the portfolio site, the bingo game, and backend features that support them.',
                ],
                [
                    'period' => 'Recent work',
                    'title' => 'Product-focused Project Builder',
                    'description' => 'Shipping deployable projects that emphasize simple UX, dependable backend logic, and practical delivery over unnecessary complexity.',
                ],
            ],
            'education' => [
                [
                    'period' => 'Self-directed learning',
                    'title' => 'CodeIgniter, PHP, and deployment practice',
                    'description' => 'Continuing to strengthen framework usage, deployment setup, and the habits needed to move a project from local development to production.',
                ],
            ],
        ];

        return view('portfolio', $portfolio);
    }

    public function stylesheet()
    {
        $cssPath = ROOTPATH . 'public/portfolio.css';

        if (! is_file($cssPath)) {
            return $this->response
                ->setStatusCode(404)
                ->setContentType('text/plain', 'utf-8')
                ->setBody('/* portfolio.css not found */');
        }

        $css = file_get_contents($cssPath);

        return $this->response
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setContentType('text/css', 'utf-8')
            ->setBody($css === false ? '/* Unable to load CSS */' : $css);
    }
}