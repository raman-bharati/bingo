<?php

namespace App\Controllers;

class Portfolio extends BaseController
{
    public function index(): string
    {
        // Centralize placeholder content so customization is straightforward.
        $portfolio = [
            'site' => [
                'name' => 'Your Name',
                'role' => 'Full-Stack Developer',
                'tagline' => 'I build reliable web products with clean UX and pragmatic engineering.',
                'location' => 'Kathmandu, Nepal',
                'email' => 'hello@example.com',
                'resumeUrl' => '#',
                'githubUrl' => 'https://github.com/yourusername',
                'linkedinUrl' => 'https://www.linkedin.com/in/yourusername/',
                'gameUrl' => 'https://example.com/my-game',
            ],
            'about' => 'This single-page portfolio is a starter you can deploy immediately. Replace the placeholder copy with your own background, strengths, and the kind of work you want to attract.',
            'skills' => [
                'PHP / CodeIgniter 4',
                'JavaScript / TypeScript',
                'REST APIs',
                'MySQL',
                'UI implementation',
                'Performance optimization',
            ],
            'projects' => [
                [
                    'title' => 'Featured Game Project',
                    'summary' => 'A real-time multiplayer game experience with room management, live state updates, and a focused interface for fast play.',
                    'link' => 'https://example.com/my-game',
                    'linkLabel' => 'Play the game',
                ],
                [
                    'title' => 'Project Placeholder One',
                    'summary' => 'Describe a project that shows technical depth, clear business impact, or thoughtful product execution.',
                    'link' => '#',
                    'linkLabel' => 'Add project link',
                ],
                [
                    'title' => 'Project Placeholder Two',
                    'summary' => 'Use this spot for another case study, client build, API integration, dashboard, or automation project.',
                    'link' => '#',
                    'linkLabel' => 'Add project link',
                ],
            ],
            'experience' => [
                [
                    'period' => '2024 - Present',
                    'title' => 'Senior Developer Placeholder',
                    'description' => 'Summarize your role, the systems you own, and the outcomes you improved for users or the business.',
                ],
                [
                    'period' => '2022 - 2024',
                    'title' => 'Developer Placeholder',
                    'description' => 'Highlight collaboration, delivery speed, reliability work, or major features shipped.',
                ],
            ],
            'education' => [
                [
                    'period' => '2020 - 2024',
                    'title' => 'Degree / Program Placeholder',
                    'description' => 'Add your institution, field of study, certifications, or notable academic work.',
                ],
            ],
        ];

        return view('portfolio', $portfolio);
    }
}