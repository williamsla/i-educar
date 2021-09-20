<?php

namespace App\Http\Controllers;

use App\Menu;
use App\Services\MenuCacheService;
use iEducar\Support\Navigation\Breadcrumb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $beta = false;
    private Breadcrumb $breadcrumb;
    private MenuCacheService $menuCacheService;

    public function __construct() {
        $this->breadcrumb = app(Breadcrumb::class);
        $this->menuCacheService = app(MenuCacheService::class);
    }

    /**
     * Set the breadcrumbs of the action
     *
     * @param       $currentPage
     * @param array $pages
     *
     * @return $this
     */
    public function breadcrumb($currentPage, $pages = [])
    {
        $breadcrumb = $this->breadcrumb
            ->current($currentPage, $pages);

        if ($this->beta) {
            $breadcrumb->addBetaFlag();
        }

        return $this;
    }

    /**
     * Share with view, title, mainmenu and menu links.
     *
     * @param int $process
     *
     * @return $this
     */
    public function menu($process)
    {
        $user = Auth::user();
        $menu = $this->menuCacheService->getMenuByUser($user);

        $topmenu = Menu::query()
            ->where('process', $process)
            ->first();

        if ($topmenu) {
            View::share('mainmenu', $topmenu->root()->getKey());
        }

        View::share('menu', $menu);
        View::share('title', '');

        return $this;
    }
}
