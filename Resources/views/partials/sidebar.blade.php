<div class="conv-sidebar-block">
    <div class="panel-group accordion accordion-empty">
        <div class="panel-heading hide">
            <h4 class="panel-title" id="swh-title"></h4>
        </div>
        <div class="panel-body">
            <div class="panel panel-default hide" id="swh-content"
                data-webhook-url="{{ $webhook_url }}"
                data-conversation-id="{{ $conversation_id }}"
                data-conversation-status="{{ $conversation_status }}"
                @if (!empty($conversation_status_name))
                    data-conversation-status-name="{{ $conversation_status_name }}"
                @endif
            ></div>
            <div class="panel panel-default" id="swh-loader">
                <img src="{{ asset('img/loader-tiny.gif') }}" />
            </div>
            <div class="margin-top-10 small">
                <a href="#" class="swh-refresh sidebar-block-link"><i class="glyphicon glyphicon-refresh"></i> {{ __("Refresh") }}</a>
            </div>
        </div>
    </div>
</div>
