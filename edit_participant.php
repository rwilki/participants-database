<?php 
// submission processing happens in Participants_Db::process_page_request on the admin_init action
//
// this file is called by the admin, also by the sortcode [edit_record]
//

if ( ! isset( $participant_id ) ) {
  // if there is no id in the request, use the default record
  $participant_id = isset( $_REQUEST['id'] ) ? $_REQUEST['id'] : Participants_Db::$id_base_number;
}

if ( $participant_id == Participants_Db::$id_base_number ) {
	
  $action = 'insert';
  $page_title = __('Add New Participant Record', Participants_Db::PLUGIN_NAME );
	
} else {
	
  $action = 'update';
  $page_title = __('Edit Existing Participant Record', Participants_Db::PLUGIN_NAME );
	
}

// get the participant information
// and run the rest of the script if the id is valid
// if this returns false, we have an invlaid ID; do nothing
if ( $participant_values = Participants_Db::get_participant( $participant_id ) ) :

if ( $participant_id == Participants_Db::$id_base_number ) $participant_values = Participants_Db::set_initial_record($participant_values);

//get the groups info
$groups = Participants_Db::get_groups();

// get the current user's info
get_currentuserinfo();

$options = get_option( self::$participants_db_options );

$section = '';
?>
<div class="wrap edit-participant">
<?php if ( is_admin() ) : ?><h2><?php echo $page_title?></h2><?php endif ?>
<?php
if ( is_object( Participants_Db::$validation_errors ) ) {
	
	echo Participants_Db::$validation_errors->get_error_html();
	
}

?>
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>" enctype="multipart/form-data" >
	<?php 
	FormElement::print_hidden_fields( array(
																					'action' => $action, 
																					'id' => ( isset( $participant_values[ 'id' ] ) ? $participant_values[ 'id' ] : $participant_id ),
																					'private_id' => $participant_values[ 'private_id' ],
																					'source' => Participants_Db::PLUGIN_NAME,
																					) );
																					
	// get the columns and output form
	$type = is_admin() ? 'backend' : 'frontend';
	$readonly_columns = Participants_Db::get_readonly();
	foreach ( Participants_db::get_column_atts( $type ) as $column ) :

    $id_line = '';
		
		// set a new section
		if ( $column->group != $section ) {
		  if ( ! empty( $section ) ) {
?>
</table>
<?php
		  } elseif ( Participants_Db::backend_user() ) {
        $id_line = '<tr><th>'._x('ID','abbreviation for "identification"',Participants_Db::PLUGIN_NAME).'</th><td>'.($participant_id == Participants_Db::$id_base_number ? _x('(new record)','indicates a new record is being entered',Participants_Db::PLUGIN_NAME) : $participant_id ).'</td></tr>';
		  }
		  $section = $column->group
?>
<h3><?php echo $groups[$section]['title']?></h3>
<?php if ( $options['show_group_descriptions'] ) echo '<p class="'.Participants_Db::$css_prefix.'group-description">'.$groups[$section]['description'].'</p>' ?>
<table class="form-table">
<?php
		  
		}
    echo $id_line;
?>

	<tr class="<?php echo $column->form_element?>">
    <?php
    $column_title = htmlspecialchars(stripslashes($column->title),ENT_QUOTES,"UTF-8",false);
    if ( $options['mark_required_fields'] && $column->validation != 'no' ) {
      $column_title = sprintf( $options['required_field_marker'], $column_title );
    }
    ?>
		<th><?php echo $column_title ?></th>
		<td id="<?php echo Participants_Db::$css_prefix.$column->name?>" >
		<?php
		
		$readonly = in_array( $column->name, $readonly_columns )  ? array( 'readonly' => 'readonly' ) : NULL;

		// get the existing value if any
		$value = isset( $participant_values[ $column->name ] ) ? Participants_Db::unserialize_array( $participant_values[ $column->name ] ) : '';
		
		// replace it with the new value if provided
		if ( isset( $_POST[ $column->name ] ) ) {
			
			if ( is_array( $_POST[ $column->name ] ) ) $value = $_POST[ $column->name ];
			
			elseif  ( Participants_Db::backend_user() && 'textarea' == $column->form_element && $options['rich_text_editor'] ) $value = $_POST[ $column->name ];

			else $value = esc_html( stripslashes( $_POST[ $column->name ] ) );
			
		}
		
		// format the date if it's a date field
		if ( 'date' == $column->form_element && ! empty( $value ) ) {
		
			$time = preg_match( '#^[0-9-]+$#', $value ) > 0 ? (int) $value : strtotime( $value );
		
			$value = date( get_option( 'date_format' )/*.' '.get_option( 'time_format' )*/, $time );
			
		}

		if ( Participants_Db::backend_user() && 'textarea' == $column->form_element && $options['rich_text_editor'] ) {

      wp_editor(
                $value,
                preg_replace( '#[0-9_-]#', '', Participants_Db::$css_prefix.$column->name ),
                array(
                      'media_buttons' => false,
                      'textarea_name' => $column->name,
                      'editor_class'  => ( $column->validation != 'no' ? "required-field" : '' ),
                      )
                );
                
    } else {
    
		FormElement::print_element( array(
																			'type'       => $column->form_element,
																			'value'      => $value,
																			'name'       => $column->name,
																			'options'    => $column->values,
                                      'class'      => ( $column->validation != 'no' ? "required-field" : '' ),
																			'attributes' => $readonly,
																			) );

    }
    
		if ( ! empty( $column->help_text ) ) :
			?>
			<span class="helptext"><?php echo trim( $column->help_text )?></span>
			<?php endif; ?>
		</td>
	 </tr>
	 <?php

		  
		
	endforeach;
	?>
	</table>
  <table class="form-table">
    <?php if ( is_admin() ) : ?>
    <tr>
      <th><h3><?php _e('Save the Record', Participants_Db::PLUGIN_NAME )?></h3></th>
      <td class="submit-buttons"><input class="button-primary" type="submit" value="Submit" name="submit">
        <input class="button-primary" type="submit" value="Apply" name="submit">
        <input class="button-primary" type="submit" value="Next" name="submit">
      </td>
    </tr>
    <tr>
      <td colspan="2"><?php _e('<strong>Submit:</strong> save record and return to list<br><strong>Apply:</strong> save record and continue with same record<br><strong>Next:</strong> save record and then start a new one', Participants_Db::PLUGIN_NAME )?> </td>
    </tr>
    <?php else : ?>
    <tr>
      <th><h3><?php echo $options['save_changes_label']?></h3></th>
      <td class="submit-buttons">
        <input class="button-primary" type="submit" value="<?php echo $options['save_changes_button']?>" name="save">
        <input name="submit" type="hidden" value="Apply">
      </td>
    </tr>
    <?php endif; ?>
  </table>
</form>
</div>
<?php endif?>