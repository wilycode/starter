@use('components.pagination', 'pagination')
@pagination.nav($pages, 'admin.dashboard', query: ['filter' => 'unread', 'page' => 99])
