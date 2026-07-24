<?php

class View {
    /**
     * Render a page inside a layout.
     *   View::render('pages/home', $data, 'main')
     * The page file echoes its body and may set $title, $description,
     * $bodyClass, $breadcrumbs by having them in $data.
     */
    public static function render(string $page, array $data = [], string $layout = 'main'): void {
        $pagePath   = AFT_ROOT . '/src/views/' . $page . '.php';
        $layoutPath = AFT_ROOT . '/src/views/layouts/' . $layout . '.php';
        if (!is_file($pagePath))   { http_response_code(404); $pagePath = AFT_ROOT . '/src/views/pages/404.php'; }
        if (!is_file($layoutPath)) { throw new RuntimeException("Missing layout: {$layout}"); }

        $defaults = [
            'title'       => AFT_NAME . ' — ' . AFT_TAGLINE,
            'description' => AFT_DESC,
            'bodyClass'   => '',
            'canonical'   => url(current_path()),
            'breadcrumbs' => [],
        ];
        $data = array_merge($defaults, $data);

        ob_start();
        extract($data, EXTR_SKIP);
        require $pagePath;
        $bodyContent = ob_get_clean();

        extract($data, EXTR_SKIP);
        require $layoutPath;
    }
}
