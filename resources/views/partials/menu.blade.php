@php
    /*
    |--------------------------------------------------------------------------
    | Database-Driven SACCO Staff Menu
    |--------------------------------------------------------------------------
    | This partial loads itself from sacco_menu. No controller needs to pass
    | menu data. Existing route middleware remains the final security boundary.
    */

    $menuLoadError = null;
    $saccoMenuTree = [];
    $saccoMenuSearchItems = [];

    $decodeMenuJson = static function ($value, $default = []) {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    };

    $normaliseMenuPath = static function (?string $path): string {
        $path = '/' . ltrim((string) $path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    };

    $isAbsoluteMenuUrl = static function (?string $url): bool {
        return is_string($url)
            && preg_match('#^https?://#i', trim($url)) === 1;
    };

    if (!auth()->check()) {
        $menuLoadError = 'You must be signed in to view the staff menu.';
    } elseif (!\Illuminate\Support\Facades\Schema::hasTable('sacco_menu')) {
        $menuLoadError = 'The SACCO menu migration has not been run.';
    } else {
        try {
            $currentUser = auth()->user();
            $currentPosition = (int) ($currentUser->member_position ?? 0);
            $currentRouteName = \Illuminate\Support\Facades\Route::currentRouteName();
            $currentRouteParameters = request()->route()
                ? request()->route()->parameters()
                : [];
            $currentRequestPath = $normaliseMenuPath(request()->path());

            /*
            |--------------------------------------------------------------------------
            | Rights are checked once per distinct right code during this request.
            |--------------------------------------------------------------------------
            */
            $rightCache = [];

            $userHasMenuRight = static function (?string $rightCode) use (&$rightCache): bool {
                $rightCode = trim((string) $rightCode);

                if ($rightCode === '') {
                    return true;
                }

                if (array_key_exists($rightCode, $rightCache)) {
                    return $rightCache[$rightCode];
                }

                try {
                    $rightCache[$rightCode] =
                        \App\Http\Middleware\CheckUserRights::userHasRight($rightCode);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Unable to evaluate a SACCO menu right.',
                        [
                            'right_code' => $rightCode,
                            'message' => $e->getMessage(),
                        ]
                    );

                    $rightCache[$rightCode] = false;
                }

                return $rightCache[$rightCode];
            };

            /*
            |--------------------------------------------------------------------------
            | Optional environment/configuration conditions from menu_conditions.
            |--------------------------------------------------------------------------
            | Supported operators:
            | =, ==, !=, <>, in, not_in, truthy, falsy
            */
            $menuConditionPasses = static function ($rawConditions) use ($decodeMenuJson): bool {
                $conditions = $decodeMenuJson($rawConditions, []);

                if ($conditions === []) {
                    return true;
                }

                if (isset($conditions['config'])) {
                    $conditions = [$conditions];
                }

                foreach ($conditions as $condition) {
                    if (!is_array($condition) || empty($condition['config'])) {
                        continue;
                    }

                    $actual = config((string) $condition['config']);
                    $expected = $condition['value'] ?? null;
                    $operator = strtolower(trim((string) ($condition['operator'] ?? '=')));

                    $passed = match ($operator) {
                        '=', '==' => (string) $actual === (string) $expected,
                        '!=', '<>' => (string) $actual !== (string) $expected,
                        'in' => in_array(
                            (string) $actual,
                            array_map('strval', (array) $expected),
                            true
                        ),
                        'not_in' => !in_array(
                            (string) $actual,
                            array_map('strval', (array) $expected),
                            true
                        ),
                        'truthy' => filter_var($actual, FILTER_VALIDATE_BOOLEAN),
                        'falsy' => !filter_var($actual, FILTER_VALIDATE_BOOLEAN),
                        default => false,
                    };

                    if (!$passed) {
                        return false;
                    }
                }

                return true;
            };

            /*
            |--------------------------------------------------------------------------
            | Convert a menu row into a usable URL.
            |--------------------------------------------------------------------------
            */
            $resolveMenuUrl = static function ($menu) use (
                $decodeMenuJson,
                $isAbsoluteMenuUrl
            ): ?string {
                $routeName = trim((string) ($menu->menu_route_name ?? ''));
                $storedUrl = trim((string) ($menu->menu_url ?? ''));
                $url = null;

                if (
                    $routeName !== ''
                    && \Illuminate\Support\Facades\Route::has($routeName)
                ) {
                    try {
                        $url = route(
                            $routeName,
                            $decodeMenuJson($menu->menu_route_parameters ?? null, [])
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning(
                            'Unable to generate a SACCO menu route.',
                            [
                                'menu_key' => $menu->menu_key ?? null,
                                'route_name' => $routeName,
                                'message' => $e->getMessage(),
                            ]
                        );
                    }
                }

                if ($url === null && $storedUrl !== '') {
                    $url = $isAbsoluteMenuUrl($storedUrl)
                        ? $storedUrl
                        : url($storedUrl);
                }

                if ($url === null) {
                    return null;
                }

                $query = $decodeMenuJson($menu->menu_query_parameters ?? null, []);

                if ($query !== []) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . http_build_query($query);
                }

                return $url;
            };

            /*
            |--------------------------------------------------------------------------
            | Determine whether one link matches the current request.
            |--------------------------------------------------------------------------
            */
            $isMenuItemActive = static function ($menu) use (
                $currentRouteName,
                $currentRouteParameters,
                $currentRequestPath,
                $decodeMenuJson,
                $normaliseMenuPath
            ): bool {
                $routeName = trim((string) ($menu->menu_route_name ?? ''));

                if ($routeName !== '' && $routeName === $currentRouteName) {
                    $requiredRouteParameters = $decodeMenuJson(
                        $menu->menu_route_parameters ?? null,
                        []
                    );

                    foreach ($requiredRouteParameters as $key => $expected) {
                        if (
                            !array_key_exists($key, $currentRouteParameters)
                            || (string) $currentRouteParameters[$key] !== (string) $expected
                        ) {
                            return false;
                        }
                    }

                    $requiredQueryParameters = $decodeMenuJson(
                        $menu->menu_query_parameters ?? null,
                        []
                    );

                    foreach ($requiredQueryParameters as $key => $expected) {
                        if ((string) request()->query($key) !== (string) $expected) {
                            return false;
                        }
                    }

                    return true;
                }

                $resolvedUrl = $menu->_resolved_url ?? null;

                if (!$resolvedUrl) {
                    return false;
                }

                $resolvedPath = parse_url($resolvedUrl, PHP_URL_PATH);

                return $normaliseMenuPath($resolvedPath) === $currentRequestPath;
            };

            $rawMenuRows = \Illuminate\Support\Facades\DB::table('sacco_menu')
                ->where('menu_active', 'Y')
                ->where('menu_deleted', 'N')
                ->orderBy('menu_parent_id')
                ->orderBy('menu_sort_order')
                ->orderBy('menu_id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Scope, position, configuration, permission and route filtering.
            |--------------------------------------------------------------------------
            */
            $allowedMenuRows = $rawMenuRows
                ->filter(function ($menu) use (
                    $currentPosition,
                    $decodeMenuJson,
                    $userHasMenuRight,
                    $menuConditionPasses,
                    $resolveMenuUrl
                ) {
                    $scope = strtoupper(trim((string) ($menu->menu_scope ?? 'OFFICIAL')));

                    if ($scope === 'OFFICIAL' && $currentPosition !== 2) {
                        return false;
                    }

                    if ($scope === 'MEMBER' && $currentPosition !== 1) {
                        return false;
                    }

                    if ($scope === 'PUBLIC') {
                        return false;
                    }

                    $positions = array_map(
                        'intval',
                        $decodeMenuJson($menu->menu_member_positions ?? null, [])
                    );

                    if ($positions !== [] && !in_array($currentPosition, $positions, true)) {
                        return false;
                    }

                    if (!$menuConditionPasses($menu->menu_conditions ?? null)) {
                        return false;
                    }

                    if (!$userHasMenuRight($menu->menu_right_code ?? null)) {
                        return false;
                    }

                    $type = strtoupper(trim((string) ($menu->menu_type ?? 'LINK')));

                    if (!in_array($type, ['GROUP', 'DIVIDER'], true)) {
                        $menu->_resolved_url = $resolveMenuUrl($menu);

                        if (!$menu->_resolved_url) {
                            return false;
                        }
                    }

                    return true;
                })
                ->values();

            $allowedById = $allowedMenuRows->keyBy('menu_id');

            /*
            |--------------------------------------------------------------------------
            | Breadcrumb used by menu search.
            |--------------------------------------------------------------------------
            */
            $buildMenuBreadcrumb = static function ($menu) use ($allowedById): string {
                $names = [];
                $seen = [];
                $cursor = $menu;

                while ($cursor) {
                    $menuId = (int) ($cursor->menu_id ?? 0);

                    if ($menuId > 0 && isset($seen[$menuId])) {
                        break;
                    }

                    if ($menuId > 0) {
                        $seen[$menuId] = true;
                    }

                    array_unshift($names, trim((string) ($cursor->menu_name ?? '')));

                    $parentId = $cursor->menu_parent_id ?? null;

                    if (!$parentId || !$allowedById->has($parentId)) {
                        break;
                    }

                    $cursor = $allowedById->get($parentId);
                }

                return implode(' › ', array_filter($names));
            };

            /*
            |--------------------------------------------------------------------------
            | Search index includes accessible search-only links.
            |--------------------------------------------------------------------------
            */
            $saccoMenuSearchItems = $allowedMenuRows
                ->filter(function ($menu) {
                    return strtoupper((string) ($menu->menu_searchable ?? 'N')) === 'Y'
                        && strtoupper((string) ($menu->menu_http_method ?? 'GET')) === 'GET'
                        && !in_array(
                            strtoupper((string) ($menu->menu_type ?? 'LINK')),
                            ['GROUP', 'DIVIDER'],
                            true
                        )
                        && !empty($menu->_resolved_url);
                })
                ->map(function ($menu) use ($buildMenuBreadcrumb) {
                    return [
                        'id' => (int) $menu->menu_id,
                        'name' => (string) $menu->menu_name,
                        'description' => (string) ($menu->menu_description ?? ''),
                        'keywords' => (string) ($menu->menu_keywords ?? ''),
                        'breadcrumb' => $buildMenuBreadcrumb($menu),
                        'url' => (string) $menu->_resolved_url,
                        'icon' => (string) ($menu->menu_icon ?? ''),
                        'weight' => (int) ($menu->menu_search_weight ?? 100),
                        'newTab' => strtoupper(
                            (string) ($menu->menu_open_new_tab ?? 'N')
                        ) === 'Y',
                    ];
                })
                ->values()
                ->all();

            /*
            |--------------------------------------------------------------------------
            | Visible recursive tree.
            |--------------------------------------------------------------------------
            */
            $visibleMenuRows = $allowedMenuRows
                ->filter(function ($menu) {
                    return strtoupper((string) ($menu->menu_visible ?? 'N')) === 'Y';
                })
                ->values();

            $rowsByParent = [];

            foreach ($visibleMenuRows as $menu) {
                $parentKey = $menu->menu_parent_id === null
                    ? 0
                    : (int) $menu->menu_parent_id;

                $rowsByParent[$parentKey][] = $menu;
            }

            foreach ($rowsByParent as &$siblings) {
                usort($siblings, static function ($left, $right) {
                    $sortComparison =
                        ((int) $left->menu_sort_order)
                        <=> ((int) $right->menu_sort_order);

                    return $sortComparison !== 0
                        ? $sortComparison
                        : ((int) $left->menu_id <=> (int) $right->menu_id);
                });
            }
            unset($siblings);

            $buildMenuTree = function (int $parentId = 0) use (
                &$buildMenuTree,
                $rowsByParent,
                $isMenuItemActive
            ): array {
                $nodes = [];

                foreach ($rowsByParent[$parentId] ?? [] as $menu) {
                    $menuId = (int) $menu->menu_id;
                    $children = $buildMenuTree($menuId);
                    $type = strtoupper((string) ($menu->menu_type ?? 'LINK'));

                    $menu->_children = $children;
                    $menu->_active = $isMenuItemActive($menu);
                    $menu->_branch_active =
                        $menu->_active
                        || collect($children)->contains(
                            fn ($child) => (bool) ($child->_branch_active ?? false)
                        );

                    /*
                     * Remove empty groups after permissions/conditions/routes
                     * have removed all of their children.
                     */
                    if ($type === 'GROUP' && $children === []) {
                        continue;
                    }

                    $nodes[] = $menu;
                }

                return $nodes;
            };

            $saccoMenuTree = $buildMenuTree();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                'Unable to load the database-driven SACCO menu.',
                [
                    'user_id' => auth()->id(),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            $menuLoadError = 'The menu could not be loaded. Check the application log.';
            $saccoMenuTree = [];
            $saccoMenuSearchItems = [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Recursive menu HTML renderer
    |--------------------------------------------------------------------------
    */
    $renderSaccoMenu = function (array $nodes, int $depth = 0) use (
        &$renderSaccoMenu
    ): string {
        $html = '';

        foreach ($nodes as $menu) {
            $type = strtoupper(trim((string) ($menu->menu_type ?? 'LINK')));
            $children = $menu->_children ?? [];
            $hasChildren = $children !== [];
            $isActive = (bool) ($menu->_active ?? false);
            $branchActive = (bool) ($menu->_branch_active ?? false);

            if ($type === 'DIVIDER') {
                $html .= '<li class="menu-divider my-2"><hr class="my-1"></li>';
                continue;
            }

            $liClasses = $depth === 0
                ? ['Ul_li--hover']
                : ['item-name'];

            if ($branchActive) {
                $liClasses[] = 'mm-active';
            }

            $liClass = implode(' ', $liClasses);
            $name = e((string) ($menu->menu_name ?? ''));
            $icon = trim((string) ($menu->menu_icon ?? ''));
            $iconSize = $depth === 0 ? 'text-20' : 'text-15';
            $iconHtml = $icon !== ''
                ? '<i class="' . e($icon) . ' ' . $iconSize
                    . ' me-2" style="color:#663399;"></i>'
                : '';

            if ($type === 'GROUP' || $hasChildren) {
                $anchorClasses = ['has-arrow'];

                if ($branchActive) {
                    $anchorClasses[] = 'active';
                }

                $childClasses = ['mm-collapse'];

                if ($branchActive) {
                    $childClasses[] = 'mm-show';
                }

                $labelHtml = $depth === 0
                    ? '<span class="item-name text-15 text-muted">' . $name . '</span>'
                    : '<span class="text-muted">' . $name . '</span>';

                $html .= '<li class="' . e($liClass) . '">';
                $html .= '<a class="' . e(implode(' ', $anchorClasses))
                    . '" href="#" aria-expanded="'
                    . ($branchActive ? 'true' : 'false') . '">';
                $html .= $iconHtml . $labelHtml;
                $html .= '</a>';
                $html .= '<ul class="' . e(implode(' ', $childClasses)) . '">';
                $html .= $renderSaccoMenu($children, $depth + 1);
                $html .= '</ul>';
                $html .= '</li>';

                continue;
            }

            $url = (string) ($menu->_resolved_url ?? '#');
            $method = strtoupper((string) ($menu->menu_http_method ?? 'GET'));
            $newTab = strtoupper(
                (string) ($menu->menu_open_new_tab ?? 'N')
            ) === 'Y';

            $labelHtml = $depth === 0
                ? '<span class="item-name text-15 text-muted">' . $name . '</span>'
                : '<span class="text-muted">' . $name . '</span>';

            $linkClasses = $isActive ? 'active' : '';
            $html .= '<li class="' . e($liClass) . '">';

            if ($method === 'GET') {
                $target = $newTab
                    ? ' target="_blank" rel="noopener noreferrer"'
                    : '';

                $html .= '<a class="' . e($linkClasses) . '" href="'
                    . e($url) . '"' . $target . '>';
                $html .= $iconHtml . $labelHtml;
                $html .= '</a>';
            } else {
                $formId = 'sacco-menu-action-' . (int) $menu->menu_id;

                $html .= '<form id="' . e($formId) . '" action="'
                    . e($url) . '" method="POST" class="m-0 sacco-menu-action-form">';
                $html .= '<input type="hidden" name="_token" value="'
                    . e(csrf_token()) . '">';

                if ($method !== 'POST') {
                    $html .= '<input type="hidden" name="_method" value="'
                        . e($method) . '">';
                }

                $html .= '<button type="submit" class="sacco-menu-action-link '
                    . e($linkClasses) . '">';
                $html .= $iconHtml . $labelHtml;
                $html .= '</button>';
                $html .= '</form>';
            }

            $html .= '</li>';
        }

        return $html;
    };

    $menuSearchJson = json_encode(
        $saccoMenuSearchItems,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
@endphp

<div class="sidebar-panel bg-white">
    <div class="gull-brand pe-3 text-center mt-4 mb-2 d-flex justify-content-center align-items-center">
        <a href="{{ url('/') }}" style="text-decoration:none;">
            @php
                $currentDomain = parse_url(url('/'), PHP_URL_HOST);

                if (in_array($currentDomain, ['127.0.0.1', 'localhost'], true)) {
                    $currentDomain = 'default';
                }

                $domainLogoPath = '/image/' . $currentDomain . '.jpg';
                $companyForLogo = trim(
                    (string) ($defaultCompanyName ?? config('app.name', 'SACCO'))
                );
                $nameParts = preg_split('/\s+/', $companyForLogo) ?: [];
                $firstNamePart = $nameParts[0] ?? '';
                $secondNamePart = $nameParts[1] ?? '';
            @endphp

            @if (file_exists(public_path($domainLogoPath)))
                <img
                    src="{{ asset($domainLogoPath) }}"
                    alt="{{ $companyForLogo }} logo"
                    style="height:50px;"
                >
            @else
                <span
                    style="
                        margin-left:10px;
                        font-size:19px;
                        font-weight:900;
                        font-family:'Montserrat',sans-serif;
                        background:linear-gradient(to right,rebeccapurple,indigo);
                        -webkit-background-clip:text;
                        color:transparent;
                        text-shadow:2px 2px 4px rgba(0,0,0,.2);
                    "
                >
                    <span class="logo-container">
                        <span class="adom">{{ $firstNamePart }}</span>
                        <span class="sacco">{{ $secondNamePart }}</span>
                    </span>
                </span>
            @endif
        </a>

        <div class="sidebar-compact-switch ms-auto">
            <span></span>
        </div>
    </div>

    @if ($menuLoadError === null && $saccoMenuSearchItems !== [])
        <div class="sacco-menu-search-wrap px-3 mb-2 position-relative">
            <label for="sacco-menu-search" class="visually-hidden">
                Search menu
            </label>

            <div class="position-relative">
                <i
                    class="i-Magnifi-Glass1 sacco-menu-search-icon"
                    aria-hidden="true"
                ></i>

                <input
                    type="search"
                    id="sacco-menu-search"
                    class="form-control form-control-sm sacco-menu-search-input"
                    placeholder="Search menu..."
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="sacco-menu-search-results"
                    aria-expanded="false"
                >
            </div>

            <div
                id="sacco-menu-search-results"
                class="sacco-menu-search-results shadow-sm"
                role="listbox"
                hidden
            ></div>
        </div>
    @endif

    <div
        class="scroll-nav ps ps--active-y"
        data-perfect-scrollbar="data-perfect-scrollbar"
        data-suppress-scroll-x="true"
        style="height:75%;"
    >
        <div class="side-nav">
            <div class="main-menu">
                @if ($menuLoadError !== null)
                    <div class="alert alert-warning mx-3 py-2 px-3 small">
                        {{ $menuLoadError }}
                    </div>
                @elseif ($saccoMenuTree === [])
                    <div class="alert alert-info mx-3 py-2 px-3 small">
                        No accessible menu items were found for your account.
                    </div>
                @else
                    <ul class="metismenu" id="menu">
                        {!! $renderSaccoMenu($saccoMenuTree) !!}
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div
        class="support-contact p-2 d-flex justify-content-between align-items-center"
        style="
            position:absolute;
            bottom:0;
            left:0;
            width:100%;
            background:linear-gradient(
                to right,
                rgba(255,255,255,.9),
                rgba(255,255,255,.9)
            );
            font-size:14px;
            border-top:1px solid rgba(102,51,153,.2);
        "
    >
        <div
            style="
                font-size:13px;
                color:rgba(102,51,153,1);
                font-weight:600;
            "
        >
            <strong>ERP provided by:</strong><br>
            Shahi Services,
            <a
                href="tel:+254722400737"
                style="
                    text-decoration:none;
                    color:rgba(102,51,153,1);
                    font-weight:bold;
                "
            >
                +254722400737
            </a>
        </div>
    </div>
</div>

<style>
    .sacco-menu-search-input {
        padding-left: 2rem;
        border-color: rgba(102, 51, 153, .25);
    }

    .sacco-menu-search-input:focus {
        border-color: rgba(102, 51, 153, .65);
        box-shadow: 0 0 0 .15rem rgba(102, 51, 153, .12);
    }

    .sacco-menu-search-icon {
        position: absolute;
        left: .65rem;
        top: 50%;
        z-index: 2;
        color: #663399;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .sacco-menu-search-results {
        position: absolute;
        top: calc(100% + .25rem);
        left: .75rem;
        right: .75rem;
        z-index: 1055;
        max-height: 360px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid rgba(102, 51, 153, .18);
        border-radius: .35rem;
    }

    .sacco-menu-search-result {
        display: block;
        padding: .65rem .75rem;
        color: #333;
        text-decoration: none;
        border-bottom: 1px solid #f0edf4;
    }

    .sacco-menu-search-result:last-child {
        border-bottom: 0;
    }

    .sacco-menu-search-result:hover,
    .sacco-menu-search-result:focus,
    .sacco-menu-search-result.is-selected {
        color: #663399;
        background: rgba(102, 51, 153, .07);
        outline: none;
    }

    .sacco-menu-search-result-name {
        display: block;
        font-weight: 700;
        line-height: 1.2;
    }

    .sacco-menu-search-result-path {
        display: block;
        margin-top: .2rem;
        color: #777;
        font-size: .72rem;
        line-height: 1.25;
    }

    .sacco-menu-search-empty {
        padding: .75rem;
        color: #777;
        font-size: .82rem;
        text-align: center;
    }

    .sacco-menu-action-form {
        width: 100%;
    }

    .sacco-menu-action-link {
        display: flex;
        align-items: center;
        width: 100%;
        padding: inherit;
        color: inherit;
        font: inherit;
        text-align: left;
        background: transparent;
        border: 0;
        cursor: pointer;
    }

    .sacco-menu-action-link:hover,
    .sacco-menu-action-link:focus,
    .sacco-menu-action-link.active {
        color: #663399;
        outline: none;
    }
</style>

<script id="sacco-menu-search-data" type="application/json">
{!! $menuSearchJson ?: '[]' !!}
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        const input = document.getElementById('sacco-menu-search');
        const resultsBox = document.getElementById('sacco-menu-search-results');
        const dataElement = document.getElementById('sacco-menu-search-data');

        if (!input || !resultsBox || !dataElement) {
            return;
        }

        let menuItems = [];

        try {
            menuItems = JSON.parse(dataElement.textContent || '[]');
        } catch (error) {
            console.error('Unable to read the SACCO menu search index.', error);
            return;
        }

        let selectedIndex = -1;
        let currentResults = [];

        const normalise = function (value) {
            return String(value || '')
                .toLowerCase()
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        };

        const escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const closeResults = function () {
            resultsBox.hidden = true;
            resultsBox.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
            selectedIndex = -1;
            currentResults = [];
        };

        const calculateScore = function (item, term) {
            const name = normalise(item.name);
            const keywords = normalise(item.keywords);
            const description = normalise(item.description);
            const breadcrumb = normalise(item.breadcrumb);
            let score = Number(item.weight || 100);

            if (name === term) {
                score += 1200;
            } else if (name.startsWith(term)) {
                score += 1000;
            } else if (name.includes(term)) {
                score += 800;
            }

            if (keywords === term) {
                score += 750;
            } else if (keywords.startsWith(term)) {
                score += 650;
            } else if (keywords.includes(term)) {
                score += 500;
            }

            if (breadcrumb.includes(term)) {
                score += 300;
            }

            if (description.includes(term)) {
                score += 200;
            }

            const words = term.split(' ').filter(Boolean);

            for (const word of words) {
                if (name.includes(word)) {
                    score += 120;
                }

                if (keywords.includes(word)) {
                    score += 80;
                }

                if (breadcrumb.includes(word)) {
                    score += 40;
                }
            }

            return score;
        };

        const renderResults = function (term) {
            const query = normalise(term);

            if (query.length < 2) {
                closeResults();
                return;
            }

            currentResults = menuItems
                .map(function (item) {
                    return {
                        item: item,
                        score: calculateScore(item, query)
                    };
                })
                .filter(function (result) {
                    const haystack = normalise(
                        [
                            result.item.name,
                            result.item.keywords,
                            result.item.description,
                            result.item.breadcrumb
                        ].join(' ')
                    );

                    return haystack.includes(query)
                        || query.split(' ').every(function (word) {
                            return haystack.includes(word);
                        });
                })
                .sort(function (left, right) {
                    if (right.score !== left.score) {
                        return right.score - left.score;
                    }

                    return String(left.item.name).localeCompare(
                        String(right.item.name)
                    );
                })
                .slice(0, 12)
                .map(function (result) {
                    return result.item;
                });

            selectedIndex = -1;
            input.setAttribute('aria-expanded', 'true');
            resultsBox.hidden = false;

            if (currentResults.length === 0) {
                resultsBox.innerHTML =
                    '<div class="sacco-menu-search-empty">'
                    + 'No matching menu was found.'
                    + '</div>';
                return;
            }

            resultsBox.innerHTML = currentResults
                .map(function (item, index) {
                    const target = item.newTab
                        ? ' target="_blank" rel="noopener noreferrer"'
                        : '';

                    return (
                        '<a'
                        + ' class="sacco-menu-search-result"'
                        + ' role="option"'
                        + ' data-index="' + index + '"'
                        + ' href="' + escapeHtml(item.url) + '"'
                        + target
                        + '>'
                        + '<span class="sacco-menu-search-result-name">'
                        + escapeHtml(item.name)
                        + '</span>'
                        + '<span class="sacco-menu-search-result-path">'
                        + escapeHtml(item.breadcrumb || item.description)
                        + '</span>'
                        + '</a>'
                    );
                })
                .join('');
        };

        const updateSelection = function () {
            const links = resultsBox.querySelectorAll(
                '.sacco-menu-search-result'
            );

            links.forEach(function (link, index) {
                const selected = index === selectedIndex;

                link.classList.toggle('is-selected', selected);
                link.setAttribute('aria-selected', selected ? 'true' : 'false');

                if (selected) {
                    link.scrollIntoView({
                        block: 'nearest'
                    });
                }
            });
        };

        input.addEventListener('input', function () {
            renderResults(input.value);
        });

        input.addEventListener('keydown', function (event) {
            if (resultsBox.hidden || currentResults.length === 0) {
                if (event.key === 'Escape') {
                    closeResults();
                }

                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                selectedIndex = Math.min(
                    selectedIndex + 1,
                    currentResults.length - 1
                );
                updateSelection();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateSelection();
            } else if (event.key === 'Enter' && selectedIndex >= 0) {
                event.preventDefault();

                const selectedLink = resultsBox.querySelector(
                    '.sacco-menu-search-result[data-index="'
                    + selectedIndex
                    + '"]'
                );

                if (selectedLink) {
                    selectedLink.click();
                }
            } else if (event.key === 'Escape') {
                closeResults();
            }
        });

        resultsBox.addEventListener('mousedown', function (event) {
            /*
             * Prevent input blur from closing the results before link click.
             */
            event.preventDefault();
        });

        resultsBox.addEventListener('click', function (event) {
            const link = event.target.closest('.sacco-menu-search-result');

            if (!link) {
                return;
            }

            const href = link.getAttribute('href');
            const target = link.getAttribute('target');

            closeResults();

            if (target === '_blank') {
                window.open(href, '_blank', 'noopener,noreferrer');
            } else {
                window.location.href = href;
            }
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.sacco-menu-search-wrap')) {
                closeResults();
            }
        });
    });
</script>
