@component nav($pages, $routeName, $query = [])
    @if($pages->lastPage() > 1)
        <nav class="pagination" aria-label="{{ 'pagination.label' | gtrans }}">
            @if($pages->page > 1)
                <a href="@route($routeName, [], array_merge($query, ['page' => $pages->page - 1]))" rel="prev">{{ 'pagination.previous' | gtrans }}</a>
            @endif
            <span>{{ 'pagination.page' | gtrans }} {{ $pages->page }} / {{ $pages->lastPage() }}</span>
            @if($pages->page < $pages->lastPage())
                <a href="@route($routeName, [], array_merge($query, ['page' => $pages->page + 1]))" rel="next">{{ 'pagination.next' | gtrans }}</a>
            @endif
        </nav>
    @endif
@endcomponent
