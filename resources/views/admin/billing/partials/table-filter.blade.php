@php
    $filterId = $id ?? sprintf('billing-filter-%s', str_replace(['[', ']'], '-', $name));
    $selected = (string) ($value ?? request()->query($name, ''));
    $preservedQuery = request()->except(array_filter([$name, $pageName ?? null]));
@endphp

<form method="GET" action="{{ url()->current() }}" class="admin-billing-table-filter">
    @foreach($preservedQuery as $queryName => $queryValue)
        @if(is_scalar($queryValue))
            <input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">
        @endif
    @endforeach

    <label for="{{ $filterId }}" class="admin-billing-filter-label">{{ $label ?? 'Status' }}</label>
    <select id="{{ $filterId }}" name="{{ $name }}" class="form-control input-sm" onchange="this.form.submit()">
        <option value="">{{ $placeholder ?? 'All statuses' }}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" {{ $selected === (string) $optionValue ? 'selected' : '' }}>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
</form>
