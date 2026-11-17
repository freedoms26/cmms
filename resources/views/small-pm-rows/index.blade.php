@extends('layouts.app')

@section('title', 'Small PM Rows')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = ! auth()->user()->isSupervisor();
        $hasFilters = collect(request()->only(['search', 'area_id']))->filter()->isNotEmpty();
        $ir = session('small_pm_row_import_result');
        $actions = fn ($row, $mobile = false) => ['view' => route('small-pm-rows.show', $row), 'mobile' => $mobile]
            + ($canManage ? ['edit' => route('small-pm-rows.edit', ['small_pm_row' => $row->id, 'page' => request('page')]), 'delete' => route('small-pm-rows.destroy', $row), 'confirm' => 'Hapus row?'] : []);
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Small PM Rows" description="Machine groups per row for Small PM. The order decides how schedules are generated.">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('small-pm-rows.create') }}">Add Row</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('small-pm-rows.import'), 'id' => 'rowFileInput', 'fileLabel' => 'Row import file', 'template' => route('import-templates.download', 'small-pm-row')])
        @endif

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if ($ir)
            <x-ui.alert :tone="$ir['skipped'] > 0 ? 'warning' : 'success'" title="Import result">
                <span class="font-semibold">{{ $ir['rows_created'] }}</span> new rows,
                <span class="font-semibold">{{ $ir['assigned'] }}</span> machines added to a row,
                <span class="font-semibold">{{ $ir['moved'] }}</span> machines moved to another row,
                {{ $ir['unchanged'] }} already correct,
                <span class="font-semibold">{{ $ir['skipped'] }}</span> skipped.
                @if ($ir['errors'])
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-xs">
                        @foreach ($ir['errors'] as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                        @if ($ir['skipped'] > count($ir['errors']))
                            <li>and {{ $ir['skipped'] - count($ir['errors']) }} other rows.</li>
                        @endif
                    </ul>
                @endif
            </x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="spr-search" class="{{ $label }}">Search</label>
                <input id="spr-search" type="text" name="search" value="{{ request('search') }}" placeholder="Row name" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-40">
                <label for="spr-area" class="{{ $label }}">Area</label>
                <select id="spr-area" name="area_id" class="select lg:min-h-9">
                    <option value="">All Area</option>
                    @foreach ($areas as $a)
                        <option value="{{ $a->id }}" @selected(request('area_id') == $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="spr-sort" class="{{ $label }}">Sort</label>
                <select id="spr-sort" name="sort" class="select lg:min-h-9">
                    <option value="">Default Sort</option>
                    <option value="name_asc" @selected(request('sort') == 'name_asc')>Row Name A-Z</option>
                    <option value="name_desc" @selected(request('sort') == 'name_desc')>Row Name Z-A</option>
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('small-pm-rows.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($rows->isEmpty())
            <x-ui.empty-state title="No row found"
                :description="$hasFilters ? 'Change the filters, or reset them to see every row.' : ($canManage ? 'Add rows one at a time, or import a file using the template above.' : null)" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $rows->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Nama Row</th>
                            <th scope="col">Area</th>
                            <th scope="col" class="text-right">Machine Count</th>
                            <th scope="col"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="small-pm-rows">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="font-semibold text-text">{{ $row->name }}</td>
                                <td>{{ $row->area->name ?? '-' }}</td>
                                <td class="text-right tabular-nums">{{ $row->machines_count }}</td>
                                <td>@include('partials.master.row-actions', $actions($row))</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="small-pm-rows">
            @foreach ($rows as $row)
                <article class="card min-w-0 p-4">
                    <h2 class="break-words text-base font-semibold text-text">{{ $row->name }}</h2>
                    <p class="text-sm text-text-muted">{{ $row->area->name ?? 'No area' }} · {{ $row->machines_count }} machinin</p>
                    <div class="mt-3 border-t border-border pt-3">@include('partials.master.row-actions', $actions($row, true))</div>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$rows" key="small-pm-rows" />
    </div>
@endsection
