function swh_load_content() {
    $('#swh-title').html('');
    $('#swh-title').parent().addClass('hide');
    $('#swh-content').addClass('hide');
    $('#swh-loader').removeClass('hide');

	fsAjax({
			action: 'loadSidebar',
            mailbox_id: getGlobalAttr('mailbox_id'),
            conversation_id: getGlobalAttr('conversation_id')
		},
		laroute.route('sidebarwebhook.ajax'),
		function(response) {
            if (typeof(response.status) != "undefined" && response.status == 'success' && typeof(response.html) != "undefined" && response.html) {
                $('#swh-content').html(response.html);
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
                showAjaxError(response);
            }
		}, true
	);
}

function bindSidebarForms() {
    $('#swh-content').find('form').each(function() {
        ensureSidebarConversationFields(this);
        $(this).off('submit.swh').on('submit.swh', function(e) {
            e.preventDefault();
            var form = this;
            ensureSidebarConversationFields(form);
            var formAction = form.getAttribute('action') || form.action;
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
    var conversationData = getSidebarConversationData();
    if (!conversationData.conversationId || !conversationData.conversationStatus) {
        return;
    }

    setHiddenInput(form, 'conversationId', conversationData.conversationId);
    setHiddenInput(form, 'conversationStatus', conversationData.conversationStatus);
    setHiddenInput(form, 'conversationStatusName', conversationData.conversationStatusName);
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

function getSidebarConversationData() {
    var container = document.getElementById('swh-content');
    var dataset = container ? container.dataset : {};

    return {
        conversationId: dataset && dataset.conversationId ? dataset.conversationId : getGlobalAttr('conversation_id'),
        conversationStatus: dataset && dataset.conversationStatus ? dataset.conversationStatus : getGlobalAttr('conversation_status'),
        conversationStatusName: dataset && dataset.conversationStatusName ? dataset.conversationStatusName : (getGlobalAttr('conversation_status_name') || '')
    };
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

    const WEBHOOK_URL = $('#swh-content').data('webhook-url');

    document.addEventListener('click', function(e) {
        const el = e.target.closest('[data-sidebar-action]');
        if (!el) {
            return;
        }

        e.preventDefault();

        const payload = {
            sidebar_action: el.dataset.sidebarAction,
            ticket_id: el.dataset.ticketId,
            product_id: el.dataset.productId,
            ticket_product_id: el.dataset.ticketProductId
        };

        console.log('Sidebar action', payload);

        if (!WEBHOOK_URL) {
            console.warn('Sidebar webhook URL is not configured');
            return;
        }

        fetch(WEBHOOK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function() {
            window.location.reload();
        })
        .catch(function(error) {
            console.error('Sidebar action failed', error);
        });
    });

    swh_load_content();

    $('.swh-refresh').click(function(e) {
        e.preventDefault();
        swh_load_content();
    });
});
