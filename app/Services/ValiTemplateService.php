<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ValiTemplateService
{
    /** @var array<string, string> */
    private array $allowedPages = [
        'index',
        'bootstrap-components',
        'ui-cards',
        'widgets',
        'charts',
        'form-components',
        'form-custom',
        'form-samples',
        'form-notifications',
        'table-basic',
        'table-data-table',
        'blank-page',
        'page-user',
        'page-invoice',
        'page-calendar',
        'page-mailbox',
        'page-error',
    ];

    /** @var array<string, string> */
    private array $authPages = [
        'page-lockscreen',
    ];

    /**
     * @return array{title: string, content: string, scripts: string}
     */
    public function renderAppPage(string $page): array
    {
        if (! in_array($page, $this->allowedPages, true)) {
            throw new InvalidArgumentException("Unknown Vali page: {$page}");
        }

        return $this->parsePage($page);
    }

    /**
     * @return array{title: string, content: string, scripts: string}
     */
    public function renderAuthPage(string $page): array
    {
        if (! in_array($page, $this->authPages, true)) {
            throw new InvalidArgumentException("Unknown Vali auth page: {$page}");
        }

        $html = $this->loadHtml($page);

        preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch);
        preg_match('/(<section class="material-half-bg">.*)(<!-- Essential javascripts|<script)/s', $html, $contentMatch);

        $title = trim(str_replace([' - Vali Admin', 'Vali Admin - '], '', $titleMatch[1] ?? 'Page'));

        $content = $this->rewriteAssets(trim($contentMatch[1] ?? ''));
        $content = str_replace('<h1>Vali</h1>', '<h1>'.e(config('app.name', 'Hotel SaaS')).'</h1>', $content);

        return [
            'title' => $title,
            'content' => $content,
            'scripts' => $this->extractScripts($html),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function appPages(): array
    {
        return $this->allowedPages;
    }

    /**
     * @return array{title: string, content: string, scripts: string}
     */
    private function parsePage(string $page): array
    {
        $html = $this->loadHtml($page);

        preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch);
        preg_match('/<main class="app-content">(.*?)<\/main>/s', $html, $contentMatch);

        $title = trim(str_replace([' - Vali Admin', 'Vali Admin - '], '', $titleMatch[1] ?? 'Page'));

        return [
            'title' => $title,
            'content' => $this->rewriteAssets(trim($contentMatch[1] ?? '')),
            'scripts' => $this->extractScripts($html),
        ];
    }

    private function loadHtml(string $page): string
    {
        $path = resource_path("vali-master/docs/{$page}.html");

        if (! File::exists($path)) {
            throw new InvalidArgumentException("Vali template file not found: {$page}");
        }

        return File::get($path);
    }

    private function extractScripts(string $html): string
    {
        if (! preg_match('/<!-- Page specific javascripts-->(.*?)(?:<!-- Google analytics script-->|<\/body>)/s', $html, $match)) {
            return '';
        }

        return $this->rewriteAssets(trim($match[1]));
    }

    private function rewriteAssets(string $html): string
    {
        $jsBase = asset('panel-assets/js');
        $cssBase = asset('panel-assets/css');

        $html = str_replace('href="css/', 'href="'.$cssBase.'/', $html);
        $html = str_replace('src="js/', 'src="'.$jsBase.'/', $html);
        $html = str_replace("href='css/", "href='".$cssBase.'/', $html);
        $html = str_replace("src='js/", "src='".$jsBase.'/', $html);

        $replacements = [
            'href="index.html"' => 'href="'.route('dashboard').'"',
            'action="index.html"' => 'action="'.route('dashboard').'"',
            'href="page-login.html"' => 'href="'.route('login').'"',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
}
