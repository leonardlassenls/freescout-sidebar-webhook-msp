function swh_load_content() {
    $('#swh-title').html('');
    $('#swh-title').parent().addClass('hide');
    $('#swh-content').addClass('hide');
    $('#swh-loader').removeClass('hide');

    var actionUrl = getSidebarActionUrl();
    var payload = getSidebarPayload('render');

    if (!actionUrl) {
        showAjaxError({ msg: 'Sidebar action URL is not configured' });
        return;
    }

    fetch(actionUrl, {
        method: 'POST',
        body: payload,
        credentials: 'include',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(html) {
        if (html) {
            $('#swh-content').html(html);
            bindSidebarForms();

            // Find a <title> element inside the response and display it
            title = $('#swh-content').find('title').first().text();

            if (title) {
                $('#swh-title').html(title);
                $('#swh-title').parent().removeClass('hide');
            }

            $('#swh-loader').addClass('hide');
            $('#swh-content').removeClass('hide');
        } else {
            showAjaxError({ msg: 'Sidebar response was empty' });
        }
    })
    .catch(function(error) {
        console.error('Sidebar load failed', error);
        showAjaxError({ msg: 'Sidebar load failed' });
    });
}

function bindSidebarForms() {
    $('#swh-content').find('form').each(function() {
        ensureSidebarConversationFields(this);
        $(this).off('submit.swh').on('submit.swh', function(e) {
            e.preventDefault();
            var form = this;
            ensureSidebarConversationFields(form);
            var formAction = form.getAttribute('action') || form.action || getSidebarActionUrl();
            var formMethod = (form.getAttribute('method') || form.method || 'POST').toUpperCase();

            fetch(formAction, {
                method: formMethod || 'POST',
                body: new FormData(form),
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                if (html) {
                    $('#swh-content').html(html);
                    bindSidebarForms();
                } else {
                    swh_load_content();
                }
            })
            .catch(function(error) {
                console.error('Sidebar form submit failed', error);
            });
        });
    });
}

function ensureSidebarConversationFields(form) {
    var metadata = getSidebarMetadata();
    if (!metadata.conversationId || !metadata.conversationStatus) {
        return;
    }

    setHiddenInput(form, 'conversationId', metadata.conversationId);
    setHiddenInput(form, 'conversationStatus', metadata.conversationStatus);
    setHiddenInput(form, 'conversationStatusName', metadata.conversationStatusName);
    setHiddenInput(form, 'mailboxId', metadata.mailboxId);
    setHiddenInput(form, 'customerId', metadata.customerId);
}

function setHiddenInput(form, name, value) {
    var input = form.querySelector('input[name="' + name + '"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        form.appendChild(input);
    }
    input.value = value;
}

function getSidebarMetadata() {
    var container = document.getElementById('swh-content');
    var dataset = container ? container.dataset : {};

    return {
        conversationId: dataset && dataset.conversationId ? dataset.conversationId : getGlobalAttr('conversation_id'),
        conversationStatus: dataset && dataset.conversationStatus ? dataset.conversationStatus : getGlobalAttr('conversation_status'),
        conversationStatusName: dataset && dataset.conversationStatusName ? dataset.conversationStatusName : (getGlobalAttr('conversation_status_name') || ''),
        mailboxId: dataset && dataset.mailboxId ? dataset.mailboxId : getGlobalAttr('mailbox_id'),
        customerId: dataset && dataset.customerId ? dataset.customerId : getGlobalAttr('customer_id')
    };
}

function getSidebarActionUrl() {
    var container = document.getElementById('swh-content');
    return container && container.dataset ? container.dataset.actionUrl : null;
}

function getSidebarPayload(action) {
    var metadata = getSidebarMetadata();
    var payload = new FormData();
    payload.append('sidebar_action', action);
    payload.append('conversationId', metadata.conversationId || '');
    payload.append('conversationStatus', metadata.conversationStatus || '');
    payload.append('conversationStatusName', metadata.conversationStatusName || '');
    payload.append('mailboxId', metadata.mailboxId || '');
    payload.append('customerId', metadata.customerId || '');
    return payload;
}

$(document).ready(function() {
    // If we're not actually viewing a conversation, don't try to do anything.
    if (typeof(getGlobalAttr('mailbox_id')) == "undefined" || typeof(getGlobalAttr('conversation_id')) == "undefined") {
        return;
    }

    // If we don't have the #swh-content element, the server doesn't have a configured webhook URL.
    if ($('#swh-content').length == 0) {
        return;
    }

    swh_load_content();

    $('.swh-refresh').click(function(e) {
        e.preventDefault();
        swh_load_content();
    });
});
