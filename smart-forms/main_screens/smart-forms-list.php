<?php
include_once(SMART_FORMS_DIR.'smart-forms-license.php');
require_once(SMART_FORMS_DIR.'smart-forms-bootstrap.php');




if(!defined('ABSPATH'))
    die('Forbidden');

if (isset($_GET['action'])&&in_array(strtolower(strval($_GET['action'])),['debugemail','add','clone','edit','upload','delete'])) {
    $action=strval($_GET['action']);
}else
    $action="";

if($action=='debugemail')
{
    require_once SMART_FORMS_DIR.'main_screens/smart-forms-email-helper.php';
    return;
}

do_action('smart_forms_list_load');

$checkWithOptionsResult=smart_forms_check_license_with_options($error);
global $sfLValid;
$sfLValid=$checkWithOptionsResult["is_valid"];
if($action==="add"||$action=="clone"){
    global $wpdb;
    $result=$wpdb->get_var("SELECT count(*) FROM ".SMART_FORMS_TABLE_NAME);
    $checkWithOptionsResult=smart_forms_check_license_with_options($error);
    if($result>=3&&!$checkWithOptionsResult["is_valid"])
    {
        smart_forms_load_license_manager("Sorry, this version support up to three forms only");
        $action='add';
    }else{
        if($action==="add")
        {
            include(SMART_FORMS_DIR.'main_screens/smart-forms-add-new.php');
            return;
        }
    }

}

if($action=='upload')
{
    $upload_form_field_nonce='';
    if(isset($_POST['upload_form_field_nonce']))
        $upload_form_field_nonce=strval($_POST['upload_form_field_nonce']);
    if(!wp_verify_nonce($upload_form_field_nonce,'upload_form')||!current_user_can('manage_options'))
    {
        echo __("Invalid request");
        return;
    }
    include SMART_FORMS_DIR.'smart-forms-import-form.php';
}

if($action!=="edit")
{

    $page='smart_forms_menu';




	echo "<div class='bootstrap-wrapper'><h1>".__("Forms")."</h1>";
	echo sprintf(' <a href="?page=%s&action=%s" class="btn btn-default btn-success" ><span class="glyphicon glyphicon-plus" ></span>Add New</a>',esc_attr($page),'add');
    ?>

    <form style="display: inline; margin-left:10px;" id="sfFileUploadForm" method="post" enctype="multipart/form-data" target="_self" action="?page=<?php echo esc_attr($page) ?>&action=upload">
        <?php wp_nonce_field( 'upload_form', 'upload_form_field_nonce' );  ?>
        <button style="color:black !important;"  class="btn btn-default btn-default" onclick="document.getElementById('sfFileUpload').click();return false;">
            <span class="glyphicon glyphicon-plus" ></span>
            Import Form
        </button>
        <input type="file" id="sfFileUpload" name="upload_file" style="display: none;" onchange="document.getElementById('sfFileUploadForm').submit();">
    </form>
</div>

<?php

}



if (isset($_GET['id'])) {
    $form_id=intval($_GET['id']);
}else
    $form_id="";



if($action!=null&&$form_id!=null)
{
    global $wpdb;


    if($action==="delete")
    {
        $nonce='';
        if(isset($_GET['nonce']))
            $nonce=$_GET['nonce'];
        if(!wp_verify_nonce($nonce,'delete_form_'.$form_id)||!current_user_can('manage_options'))
        {
            echo 'Invalid request, please try again';
            return;
        }


        $wpdb->query($wpdb->prepare("delete from ".SMART_FORMS_TABLE_NAME." WHERE form_id=%d",$form_id));
        delete_transient("rednao_smart_forms_$form_id");
    }

    if($action==="edit"||$action=="clone")
    {
        if($action=='clone')
        {
            $nonce='';
            if(isset($_GET['nonce']))
                $nonce=$_GET['nonce'];
            if(!wp_verify_nonce($nonce,'clone_form_'.$form_id)||!current_user_can('manage_options'))
            {
                echo 'Invalid request';
                return;
            }
        }
        $result=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".SMART_FORMS_TABLE_NAME." WHERE form_id=%d",$form_id));

        if(count($result)>0)
        {
            $result=$result[0];

            $formOptions=$result->form_options;
            //$formOptions=str_replace("\\r","", str_replace("\\r","",$formOptions));
            $elementOptions=$result->element_options;
            //$elementOptions=str_replace("\\r","", str_replace("\\","\\\\",$elementOptions));

            $formClientOptions=$result->client_form_options;

            if($formClientOptions=="")
                $formClientOptions="{}";

            $formClientOptions=$formClientOptions;
           // $formClientOptions=str_replace("\\r","", str_replace("\\","\\\\",$formClientOptions));

            $script=<<<EOF
                        <script type="text/javascript" language="javascript">
                            var smartFormId="%s";
                            var smartFormsOptions=%s;
                            var smartFormsElementOptions=%s;
                            var smartFormClientOptions=%s
                        </script>
EOF;


            echo sprintf($script,($action=="clone"?"0":$result->form_id),json_encode(json_decode( $formOptions)),json_encode(json_decode($elementOptions)),json_encode(json_decode($formClientOptions)));
            include(SMART_FORMS_DIR.'main_screens/smart-forms-add-new.php');
            return;

        }


    }
}


if(!class_exists('WP_LIST_TABLE'))
{
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');
}


class RednaoForms extends WP_List_Table
{
    function get_columns()
    {
        return array(
          'form_name'=>__('Form Name'),
          'shortcode'=>__('Shortcode'),
        );
    }

    function prepare_items()
    {
        $this->_column_headers=array($this->get_columns(),array(),$this->get_sortable_columns());
        global $wpdb;
        $savedForms=$result=$wpdb->get_results("SELECT form_name,form_id,concat('[sform]',form_id,'[/sform]') shortcode FROM ".SMART_FORMS_TABLE_NAME." ORDER BY form_order ASC, form_id ASC");
        foreach($savedForms as $form)
        {
            $form->form_name=esc_html($form->form_name);
            $form->form_id=esc_html($form->form_id);
        }
        $this->items=$savedForms;
    }

    function get_sortable_columns()
    {

    }

    function column_default($item, $column_name)
    {
        return $item->$column_name;
    }

    function single_row($item) {
        echo '<tr data-form-id="' . esc_attr($item->form_id) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    function column_form_name($item) {
        $page='';
        if(isset($_GET['page']))
            $page='smart_forms_menu';

        global $sfLValid;
        $exportAction='';
       /* if($sfLValid)
            $exportAction='';
        else
            $exportAction='event.preventDefault();alert(\'Export in only supported in the full version\');';*/


        $actions = array(
            __('edit')      => sprintf('<a href="?page=%s&id=%s&action=%s">Edit</a>',$page,$item->form_id,'edit'),
            __('delete')    => sprintf('<a href="javascript:(function(event){confirm(\'Are you sure you want to delete the form?\')?(window.location=\'?page=%s&id=%s&action=%s&nonce=%s\'):\'\';})()" >Delete</a>',$page,$item->form_id,'delete',wp_create_nonce('delete_form_'.$item->form_id)),
            __('clone')    => sprintf('<a href="?page=%s&id=%s&action=%s&nonce=%s">Clone</a>',$page,$item->form_id,'clone',wp_create_nonce('clone_form_'.$item->form_id)),
            __('export')    => sprintf('<a onClick="'.$exportAction.'" target="_blank" href="'.esc_attr(admin_url('admin-ajax.php')).'?action=rednao_smart_forms_export&formId=%s'.'">Export</a>',$item->form_id,'export')
        );

        return sprintf('%1$s %2$s', $item->form_name, $this->row_actions($actions) );
    }
}

$donationList=new RednaoForms();
$donationList->prepare_items();
$donationList->display();

?>

<style>
    .sf-sortable-active tr {
        cursor: move;
    }
    .sf-sortable-active tr:hover {
        background: #f0f6fc !important;
    }
    .sf-sortable-active .ui-sortable-helper {
        background: #fff !important;
        box-shadow: 0 3px 8px rgba(0,0,0,0.18);
        outline: 2px solid #2271b1;
    }
    .sf-sortable-active .ui-sortable-placeholder {
        visibility: visible !important;
        background: #e8f0fe !important;
        border: 2px dashed #2271b1 !important;
    }
    .sf-drag-handle {
        cursor: move;
        color: #999;
        margin-right: 6px;
        display: inline-block;
        vertical-align: middle;
    }
    .sf-drag-handle:hover {
        color: #2271b1;
    }
    .sf-order-saved {
        display: inline-block;
        margin-left: 10px;
        padding: 4px 12px;
        background: #00a32a;
        color: #fff;
        border-radius: 3px;
        font-size: 13px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sf-order-saved.visible {
        opacity: 1;
    }
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var $table = $('table.wp-list-table');
    var $tbody = $table.find('tbody#the-list');

    if ($tbody.length === 0) {
        $tbody = $table.find('tbody');
    }

    if ($tbody.length === 0 || $tbody.find('tr').length <= 1) {
        return;
    }

    // Add drag handle to each row's first column
    $tbody.find('tr').each(function() {
        var $firstTd = $(this).find('td').first();
        $firstTd.prepend('<span class="sf-drag-handle dashicons dashicons-menu"></span>');
    });

    // Add "Order saved" indicator near the title
    $('h1').first().after('<span class="sf-order-saved" id="sfOrderSaved">&#10003; Order saved</span>');

    $table.addClass('sf-sortable-active');

    $tbody.sortable({
        items: 'tr',
        axis: 'y',
        placeholder: 'ui-sortable-placeholder',
        cursor: 'move',
        opacity: 0.85,
        tolerance: 'pointer',
        start: function(e, ui) {
            // Preserve column widths during drag
            ui.helper.children('td').each(function(index) {
                $(this).width($(this).width());
            });
            ui.placeholder.height(ui.helper.outerHeight());
        },
        update: function(e, ui) {
            var order = [];
            $tbody.find('tr').each(function() {
                var formId = $(this).data('form-id');
                if (formId) {
                    order.push(formId);
                }
            });

            $.post(ajaxurl, {
                action: 'rednao_smart_forms_update_order',
                nonce: '<?php echo wp_create_nonce("smart_forms_update_order"); ?>',
                order: order
            }, function(response) {
                try {
                    var result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        var $indicator = $('#sfOrderSaved');
                        $indicator.addClass('visible');
                        setTimeout(function() {
                            $indicator.removeClass('visible');
                        }, 2000);
                    }
                } catch(e) {
                    console.error('Smart Forms: Error saving order', e);
                }
            });
        }
    });
});
</script>
