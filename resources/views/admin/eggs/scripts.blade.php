@extends('layouts.admin')

@section('title')
    Nests &rarr; Egg: {{ $egg->name }} &rarr; Install Script
@endsection

@section('content-header')
    <h1>{{ $egg->name }}<small>Manage the install script for this Egg.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nests') }}">Nests</a></li>
        <li><a href="{{ route('admin.nests.view', $egg->nest->id) }}">{{ $egg->nest->name }}</a></li>
        <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">{{ $egg->name }}</a></li>
        <li class="active">{{ $egg->name }}</li>
    </ol>
@endsection

@section('content')
<div class="admin-install-script-page">
<div class="row admin-install-script-tabs-row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nests.egg.variables', $egg->id) }}">Variables</a></li>
                <li class="active"><a href="{{ route('admin.nests.egg.scripts', $egg->id) }}">Install Script</a></li>
            </ul>
        </div>
    </div>
</div>
<form action="{{ route('admin.nests.egg.scripts', $egg->id) }}" method="POST" class="admin-install-script-shell">
    <div class="row">
        <div class="col-xs-12">
            <div class="box admin-install-script-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Install Script</h3>
                </div>
                @if(! is_null($egg->copyFrom))
                    <div class="box-body">
                        <div class="callout callout-warning no-margin">
                            This service option is copying installation scripts and container options from <a href="{{ route('admin.nests.egg.view', $egg->copyFrom->id) }}">{{ $egg->copyFrom->name }}</a>. Any changes you make to this script will not apply unless you select "None" from the dropdown box below.
                        </div>
                    </div>
                @endif
                <div class="box-body no-padding admin-install-script-editor-body">
                    <div id="editor_install" class="admin-editor-md admin-install-script-editor">{{ $egg->script_install }}</div>
                </div>
                <div class="box-body admin-install-script-controls">
                    <div class="row">
                        <div class="form-group col-sm-4">
                            <label class="control-label">Copy Script From</label>
                            <select id="pCopyScriptFrom" name="copy_script_from">
                                <option value="">None</option>
                                @foreach($copyFromOptions as $opt)
                                    <option value="{{ $opt->id }}" {{ $egg->copy_script_from !== $opt->id ?: 'selected' }}>{{ $opt->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted small">If selected, script above will be ignored and script from selected option will be used in place.</p>
                        </div>
                        <div class="form-group col-sm-4">
                            <label class="control-label">Script Container</label>
                            <input type="text" name="script_container" class="form-control" value="{{ $egg->script_container }}" />
                            <p class="text-muted small">Docker container to use when running this script for the server.</p>
                        </div>
                        <div class="form-group col-sm-4">
                            <label class="control-label">Script Entrypoint Command</label>
                            <input type="text" name="script_entry" class="form-control" value="{{ $egg->script_entry }}" />
                            <p class="text-muted small">The entrypoint command to use for this script.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12 text-muted">
                            The following service options rely on this script:
                            @if(count($relyOnScript) > 0)
                                @foreach($relyOnScript as $rely)
                                    <a href="{{ route('admin.nests.egg.view', $rely->id) }}">
                                        <code>{{ $rely->name }}</code>@if(!$loop->last),&nbsp;@endif
                                    </a>
                                @endforeach
                            @else
                                <em>none</em>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <textarea name="script_install" class="hidden"></textarea>
                    <button type="submit" name="_method" value="PATCH" class="btn btn-primary btn-sm pull-right">Save</button>
                </div>
            </div>
        </div>
    </div>
</form>
</div>
@endsection

@section('footer-scripts')
    @parent
    {!! Theme::js('vendor/ace/ace.js') !!}
    {!! Theme::js('vendor/ace/ext-modelist.js') !!}
    <script>
    document.body.classList.add('admin-install-script-route');

    $(document).ready(function () {
        $('#pCopyScriptFrom').select2();

        const InstallEditor = ace.edit('editor_install');
        const Modelist = ace.require('ace/ext/modelist')

        InstallEditor.setTheme('ace/theme/chrome');
        InstallEditor.getSession().setMode('ace/mode/sh');
        InstallEditor.getSession().setUseWrapMode(true);
        InstallEditor.setShowPrintMargin(false);

        const fitInstallEditor = () => {
            const editorElement = document.getElementById('editor_install');
            if (!editorElement) {
                return;
            }

            const editorTop = editorElement.getBoundingClientRect().top;
            const availableHeight = Math.floor(window.innerHeight - editorTop);
            const nextHeight = Math.max(420, Math.floor(availableHeight * 0.9));

            editorElement.style.setProperty('height', `${nextHeight}px`, 'important');
            InstallEditor.resize();
        };

        fitInstallEditor();
        setTimeout(fitInstallEditor, 50);
        setTimeout(fitInstallEditor, 250);
        $(window).on('resize', fitInstallEditor);

        $('form').on('submit', function (e) {
            $('textarea[name="script_install"]').val(InstallEditor.getValue());
        });
    });
    </script>

@endsection
