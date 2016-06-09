var jelle_s_http_status_code = {
  statusKey: JELLE_S_HTTP_STATUS_KEY
};
(function ($, ajaxurl, statusCode) {
  // Display the http status config interface.
  statusCode.display = function (id) {
    if ($('#config-http-status-button-' + id).hasClass('disabled')) {
      return false;
    }
    add_loading('#actions-' + id + ' .button');
    var keyword = $('#keyword_' + id).val();
    var nonce = get_var_from_query($('#config-http-status-button-' + id).attr('href'), 'nonce');
    $.getJSON(
      ajaxurl,
      {action: "config_http_status_display", keyword: keyword, nonce: nonce, id: id},
      function (data) {
        $("#id-" + id).after(data.html);
        end_loading('#actions-' + id + ' .button');
      }
    );
  };

  statusCode.save = function (id) {
    add_loading("#config-http-status-close-" + id);
    var code = $('#config-http-status-http-code-' + id).val();
    var oldcode = $('#old_code_' + id).val();
    var keyword = $('#keyword_' + id).val();
    var nonce = $('#nonce_' + id).val();
    $.getJSON(
      ajaxurl,
      {action: 'config_http_status_save', id: id, keyword: keyword, nonce: nonce, code: code, oldcode: oldcode},
      function (data) {
        if (data.status == 'success') {
          $('#' + statusCode.statusKey + '-' + id).html(data.code);
        }
        $("#config-http-status-" + id).fadeOut(200, function(){
          $('#main_table tbody').trigger("update");
        });
        feedback(data.message, data.status);
        end_loading("#config-http-status-close-" + id);
        end_disable("#actions-" + id + ' .button');
      }
    );
  };

  statusCode.hide = function (id) {
    $("#config-http-status-" + id).fadeOut(200, function(){
		end_disable('#actions-'+id+' .button');
	});
  }

})(jQuery, ajaxurl, jelle_s_http_status_code);
