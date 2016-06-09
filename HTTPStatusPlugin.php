<?php
/*
  Plugin Name: HTTP status per link
  Plugin URI: https://github.com/Jelle-S/YOURLS-http-status-per-link
  Description: Set the HTTP redirect status per link.
  Version: 1.0
  Author: Jelle Sebreghts
  Author URI: https://github.com/Jelle-S
 */
// No direct call
if (!defined('YOURLS_ABSPATH')) {
  die();
}

/**
 * Plugin class.
 */
class HTTPStatusPlugin {

  /**
   * Constants.
   */
  const TABLENAME = 'jelle_s_http_status_codes';
  const HTTP_STATUS_KEY = 'jelle_s_http_status_code';
  const TABLE_ROW_FILTER = 'jelle_s_http_status_code_table_row';
  const STATUS_CODE_OPTIONS_FILTER = 'jelle_s_http_status_code_options';
  const SHUNT_SAVE_CODE = 'jelle_s_http_status_shunt_save_code';
  const PRE_SAVE_CODE = 'jelle_s_http_status_pre_save_code';
  const HTTP_STATUS_NONE = 0;

  /**
   * Registers all actions and filters for this plugin.
   *
   * @param string $pluginName
   *   The plugin name (folder name) for this plugin.
   */
  public function __construct($pluginName) {
    // Core actions.
    yourls_add_action('activated_' . $pluginName . '/plugin.php', array($this, 'onPluginActivated'));
    yourls_add_action('plugins_loaded', array($this, 'onPluginsLoaded'));
    yourls_add_action('html_head', array($this, 'onHtmlHead'));
    yourls_add_action('admin_page_before_form', array($this, 'onAdminPageBeforeForm'));
    yourls_add_action('delete_link', array($this,'onDeleteLink'));

    // Own, custom actions.
    yourls_add_action('yourls_ajax_config_http_status_display', array($this, 'onDisplayConfigForm'));
    yourls_add_action('yourls_ajax_config_http_status_save', array($this, 'onSave'));

    // Core filters.
    yourls_add_filter('table_add_row_action_array', array($this, 'alterRowActions'));
    yourls_add_filter('get_keyword_infos', array($this, 'alterKeywordInfo'));
    yourls_add_filter('table_add_row_cell_array', array($this, 'alterTableCell'));
    yourls_add_filter('table_head_cells', array($this, 'alterTableHead'));
    yourls_add_filter('table_edit_row', array($this, 'alterRowColspan'));
    yourls_add_filter('redirect_code', array($this, 'alterRedirectCode'));
  }

  /**
   * Creates the table if it doesn't exist yet.
   */
  public function onPluginActivated() {
    global $ydb;
    $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->getFullTableName() . ' (keyword VARCHAR(200) NOT NULL, PRIMARY KEY(keyword),code VARCHAR(3));';
    $ydb->query($sql);
  }

  /**
   * Registers the admin page.
   */
  public function onPluginsLoaded() {
    yourls_register_plugin_page(str_replace('_', '-', static::HTTP_STATUS_KEY), 'Default status code', array($this, 'doDisplayAdminPage') );
  }

  /**
   * Outputs our JavaScript.
   */
  public function onHtmlHead() {
    echo '<script type="text/javascript">' . "\n" . 'var JELLE_S_HTTP_STATUS_KEY = "' . static::HTTP_STATUS_KEY . '";' . "\n" . '' . file_get_contents(__DIR__ . '/js/http-status.js') . '</script>';
    echo '<style>'. file_get_contents(__DIR__ . '/css/http-status.css') . '</style>';
  }

  /**
   * Adds info about the configured default status code to the header.
   */
  public function onAdminPageBeforeForm() {
    echo '<p>' . yourls_s('Current configured default status code is: <strong>%s</strong>', $this->getCodeOptionLabel($this->getDeafaultStatusCode()));
  }

  /**
   * Delete the status code when the link is deleted.
   */
  public function onDeleteLink($args) {
    global $ydb;
    $keyword = yourls_escape(yourls_sanitize_string($args[0]));
    $ydb->query("DELETE FROM ". $this->getFullTableName() ." WHERE keyword = '$keyword';");
  }

  /**
   * AJAX callback: Displays the config form.
   */
  public function onDisplayConfigForm() {
    yourls_verify_nonce('config-http-status-link_' . $_REQUEST['id'], $_REQUEST['nonce'], FALSE, yourls__('An error occurred.'));
    $row = $this->getTableConfigRow($_REQUEST['keyword']);
    echo json_encode(array('html' => $row));
  }

  /**
   * AJAX callback: Saves the status code to the database.
   */
  public function onSave() {
    yourls_verify_nonce( 'config-http-status-save_' . $_REQUEST['id'], $_REQUEST['nonce'], FALSE, yourls__('An error occurred.'));
    $return = $this->doSave($_REQUEST['keyword'], $_REQUEST['code'], $_REQUEST['oldcode']);
    echo json_encode($return);
  }

  /**
   * Displays the admin page.
   */
  public function doDisplayAdminPage() {
    if (isset($_POST[static::HTTP_STATUS_KEY])) {
      yourls_verify_nonce(static::HTTP_STATUS_KEY, $_REQUEST['nonce'], FALSE, yourls__('An error occurred.'));
      $code = intval($_POST[static::HTTP_STATUS_KEY]);
      if ($code) {
        yourls_update_option(static::HTTP_STATUS_KEY, $code );
        echo '<script type="text/javascript">feedback("' . yourls__('Default redirect status code was saved successfully.') . '", "success");</script>';
      }
    }

    $code = $this->getDeafaultStatusCode();
    $nonce = yourls_create_nonce(static::HTTP_STATUS_KEY);
    $options = '';
    foreach ($this->getCodeOptions() as $option_code => $option) {
      $options .= '<option ' . ($option_code === $code ? 'selected="selected" ' : '') . '>' . $option . '</option>';
    }
    $status_key = static::HTTP_STATUS_KEY;
    $form = <<<FORM
    <h2>%s</h2>
    <p>%s</p>
    <form method="post">
      <input type="hidden" name="nonce" value="$nonce" />
      <label for="$status_key">%s:</label>
      <select id="$status_key" name="$status_key">
        $options
      </select>
      <input type="submit" value="%s" />
    </form>
FORM;
    echo sprintf(
      $form,
      yourls__('Default redirect status code'),
      yourls_s('This plugin allows you to configure the default redirect status code. Selecting "%s" means that this plugin will not interfere with the status code given by YOURLS or an other plugin. Selecting any other value means that this plugin will overwrite the status code with the selected default for all links, unless a specific status code for this link was selected.', $this->getCodeOptionLabel(static::HTTP_STATUS_NONE)),
      yourls__('Select default redirect status code'),
      yourls__('Save')
    );
  }

  /**
   * Adds our action to the actions for this row.
   */
  public function alterRowActions($links) {
    // Grab the id and keyword from the edit link.
    $edit = $links['edit'];
    $parameters = array();
    parse_str(parse_url($edit['href'], PHP_URL_QUERY), $parameters);
    $id = $parameters['id'];
    $keyword = $parameters['keyword'];

    // Create the link.
    $config_link = yourls_nonce_url(
      'config-http-status-link_' . $id,
      yourls_add_query_arg(array(
        'id' => $id,
        'action' => 'config_http_status_display',
        'keyword' => $keyword
      ), yourls_admin_url('admin-ajax.php'))
    );

    // Add the link to the array.
    $links['config-http-status'] = array(
      'href' => $config_link,
      'id' => "config-http-status-button-$id",
      'title' => yourls_esc_attr__('Configure HTTP status code'),
      'anchor' => yourls__('HTTP status code'),
      'onclick' => "jelle_s_http_status_code.display('$id');return false;",
    );
    return $links;
  }

  /**
   * Adds the status code to the keyword info.
   */
  public function alterKeywordInfo($info, $keyword) {
    if (!$info) {
      return $info;
    }
    $info[static::HTTP_STATUS_KEY] = $this->getStatusCode($keyword, static::HTTP_STATUS_NONE);
    return $info;
  }

  /**
   * Adds the status code table cell to each row.
   */
  public function alterTableCell($cells, $keyword) {
    return array_slice($cells, 0, count($cells) - 1, TRUE)
      + array(static::HTTP_STATUS_KEY => array(
        'template' => '%code%',
        'code' => $this->getCodeOptionLabel(yourls_get_keyword_info($keyword, static::HTTP_STATUS_KEY)),
      ))
      + array_slice($cells, count($cells) - 1, NULL, TRUE);
  }

  /**
   * Adds the 'HTTP status code' header to the table.
   */
  public function alterTableHead($head) {
    return array_slice($head, 0, count($head) - 1, TRUE)
      + array(static::HTTP_STATUS_KEY => yourls__('HTTP status code'))
      + array_slice($head, count($head) - 1, NULL, TRUE);
  }

  /**
   * Increases the colspan with 1 since we added a column to the table.
   */
  public function alterRowColspan($row) {
    // Convert the row in to a DOMDocument.
    $doc = new DOMDocument();
    $doc->loadHTML("<html><table>{$row}</table></html>");
   
    // Get the first <tr> and check its first td's colspan attribute.
    $tr = $doc->documentElement->getElementsByTagName('tr')->item(0);
    if (!$tr->firstChild->hasAttribute('colspan')) {
      $colspan = 1;
    }
    else {
      $colspan = (int) $tr->firstChild->getAttribute('colspan');
    }

    // Update the colspan attribute and return the row as HTML.
    $tr->firstChild->setAttribute('colspan', $colspan + 1);
    return $doc->saveHTML($tr);
  }

  /**
   * Sets the correct redirect code for a short url.
   */
  public function alterRedirectCode($code) {
    global $keyword;
    $configured_code = $this->getStatusCode($keyword);
    return $configured_code !== static::HTTP_STATUS_NONE ? $configured_code : $code;
  }

  /**
   * Helper function: Save a status code for a keyword.
   */
  protected function doSave($keyword, $code, $oldcode) {
    // Allow plugins to short-circuit the whole function.
	$pre = yourls_apply_filter(static::SHUNT_SAVE_CODE, NULL, $keyword, $code, $oldcode);
	if ( NULL !== $pre ) {
	  return $pre;
    }

	global $ydb;
	$code = yourls_escape(yourls_sanitize_int($code));
	$keyword = yourls_escape(yourls_sanitize_string( $keyword ));
    $table = $this->getFullTableName();

    // Allow plugins to act before saving.
	yourls_do_action(static::PRE_SAVE_CODE, $keyword, $code, $oldcode);

    // Check if we need to do an insert or update.
    $existing_record = $ydb->get_var('SELECT code FROM ' . $table . ' WHERE keyword = "' . $keyword . '";');
    $query = !is_null($existing_record) ? "UPDATE $table SET code='$code' WHERE keyword = '$keyword';" : "INSERT INTO $table (code, keyword) VALUES ('$code', '$keyword');";
    $success = $ydb->query( $query );

    // Return the right message.
    if ($success || ($oldcode == $code && $success === 0)) {
      $return['code'] = $this->getCodeOptionLabel($code);
      $return['status']  = 'success';
      $return['message'] = yourls__( 'HTTP status code updated in database' );
    }
    else {
      $return['status']  = 'fail';
      $return['message'] = /* //translators: "Error updating HTTP status code for http://sho.rt/blah" */ yourls_s('Error updating HTTP status code for %s', $keyword) ;
    }
	return yourls_apply_filter( 'edit_link', $return, $keyword, $code, $oldcode);
  }

  protected function getCodeOptionLabel($code) {
    $code_options = $this->getCodeOptions();
    return isset($code_options[$code]) ? $code_options[$code] : yourls__('Invalid status code');
  }

  /**
   * Helper function: Get available HTTP status codes as options.
   */
  protected function getCodeOptions() {
    return yourls_apply_filter(static::STATUS_CODE_OPTIONS_FILTER, array(
      static::HTTP_STATUS_NONE => yourls__('Use default status code'),
      301 => yourls__('301: Moved Permanently'),
      302 => yourls__('302: Found'),
      303 => yourls__('303: See Other'),
      307 => yourls__('307: Temporary Redirect'),
      308 => yourls__('308: Permanent Redirect'),
    ));
  }

  /**
   * Helper function: Get the default status code.
   */
  protected function getDeafaultStatusCode() {
    return intval(yourls_get_option(static::HTTP_STATUS_KEY, static::HTTP_STATUS_NONE));
  }

  /**
   * Helper function: Get the full database table name.
   */
  protected function getFullTableName() {
    return YOURLS_DB_PREFIX . static::TABLENAME;
  }

  /**
   * Helper function: Get the status code for a keyword.
   */
  protected function getStatusCode($keyword, $default = FALSE) {
    global $ydb;
    if ($default === FALSE) {
      $default = $this->getDeafaultStatusCode();
    }
    $keyword = yourls_escape( yourls_sanitize_string( $keyword ) );
    $sql = "SELECT c.code FROM ". $this->getFullTableName() ." c INNER JOIN " . YOURLS_DB_TABLE_URL . " u ON u.keyword = c.keyword WHERE u.keyword='$keyword'";
    $code = $ydb->get_var($sql);
    return (!$code || $code === static::HTTP_STATUS_NONE) ? $default : $code;
  }

  /**
   * Helper function: Get the config form as a table row.
   */
  protected function getTableConfigRow($keyword) {
    $keyword = yourls_sanitize_string($keyword);
    $id = yourls_string2htmlid($keyword);
    $code = yourls_get_keyword_info($keyword, static::HTTP_STATUS_KEY);
    $nonce = yourls_create_nonce('config-http-status-save_' . $id);
    $code_options = $this->getCodeOptions();
    if ($code || $code === static::HTTP_STATUS_NONE) {
      $code_options_html = '';
      foreach ($code_options as $code_option => $option_label) {
        $code_options_html .= '<option value="' . $code_option . '" ' . ($code_option == $code ? 'selected="selected"' : '') . '>' . $option_label . '</option>' . "\n";
      }
      $return = <<<RETURN
<tr id="config-http-status-$id" class="config-http-status-row">
  <td colspan="6" class="config-http-status-row">
    <strong>%s</strong>:
    <select id="config-http-status-http-code-$id" name="config-http-status-http-code-$id" value="$code" class="select">
      $code_options_html
    </select>
  </td>
  <td colspan="1">
    <input type="button" id="config-http-status-submit-$id" name="config-http-status-submit-$id" value="%s" title="%s" class="button" onclick="jelle_s_http_status_code.save('$id');" />&nbsp;
    <input type="button" id="config-http-status-close-$id" name="config-http-status-close-$id" value="%s" title="%s" class="button" onclick="jelle_s_http_status_code.hide('$id');" />
    <input type="hidden" id="nonce_$id" value="$nonce"/>
    <input type="hidden" id="keyword_$id" value="$keyword"/>
    <input type="hidden" id="old_code_$id" value="$code"/>
  </td>
</tr>
RETURN;
      $return = sprintf(urldecode($return), yourls__('HTTP status code'), yourls__('Save'), yourls__('Save HTTP status code'), yourls__('Cancel'), yourls__('Cancel editing'));
    }
    else {
      $return = '<tr class="config-http-status-row notfound"><td colspan="6" class="config-http-status-row notfound">' . yourls__('Error, URL not found.') . '</td></tr>';
    }

    $return = yourls_apply_filter(static::TABLE_ROW_FILTER, $return, $keyword, $code);

    return $return;
  }
}
