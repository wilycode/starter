
@component notice($message, $variant = 'info', $translate = true)
    @if($variant === 'error' || $variant === 'warning')
        <div class="notice notice-{{ $variant }}" role="alert">@if($translate){{ $message | trans }}@else{{ $message }}@endif</div>
    @else
        <div class="notice notice-{{ $variant }}" role="status">@if($translate){{ $message | trans }}@else{{ $message }}@endif</div>
    @endif
@endcomponent

@component flash($flash)
    @foreach($flash->pullAll() as $entry)
        @message.notice($entry['message'], variant: $entry['type'], translate: false)
    @endforeach
@endcomponent
