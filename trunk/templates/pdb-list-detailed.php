<?php
/*

template for participants list shortcode output

this is a more detailed template showing how the parts of the display can be customized

*/
?>
<div class="wrap <?php echo $this->wrap_class ?>">
<!-- template:<?php echo basename( __FILE__ ); // this is only to show which template is in use ?> -->
<a name="<?php echo $this->list_anchor ?>" id="<?php echo $this->list_anchor ?>"></a>
<?php

  /*
   * SEARCH/SORT FORM
   *
   * the search/sort form is ony presented when enabled in the shortcode.
   * It's also skipped when refreshing the list after a sort or filter operation
   * 
   */
  if ( $filter_mode != 'none' && ! $filtering ) : ?>
  <div class="pdb-searchform">
  
    <div class="pdb-error pdb-search-error" style="display:none">
      <p id="search_field_error"><?php _e( 'Please select a column to search in.', 'participants-database' )?></p>
      <p id="value_error"><?php _e( 'Please type in something to search for.', 'participants-database' )?></p>
    </div>

    <?php $this->search_sort_form_top(); ?>

    <?php if ( $filter_mode == 'filter' || $filter_mode == 'both' ) : ?>

    <fieldset class="widefat">
      <legend><?php _e('Search', 'participants-database' )?>:</legend>

      <?php
        // you can replace "false" with your own text for the "all columns" value
        $this->column_selector( false );
      ?>

      <?php $this->search_form() ?>
      
    </fieldset>
    <?php endif ?>
    <?php if ( $filter_mode == 'sort' || $filter_mode == 'both' ) : ?>
    
    <fieldset class="widefat">
      <legend><?php _e('Sort by', 'participants-database' )?>:</legend>

      <?php $this->sort_form() ?>

    </fieldset>
    <?php endif ?>

  </div>
  <?php endif ?>

<?php
  /* END SEARCH/SORT FORM */

  /* LIST DISPLAY */
?>

  <table class="wp-list-table widefat fixed pages" id="pdb-list" cellspacing="0" >
  
    <?php // print the count if enabled in the shortcode
		if ( $display_count ) : ?>
    <caption>
      Total Records Found: <?php echo $record_count ?>
    </caption>
    <?php endif ?>

    <?php if ( $record_count > 0 ) : ?>

    <thead>
      <tr>
        <?php /*
         * this function prints headers for all the fields
         * replacement codes:
         * %2$s is the form element type identifier
         * %1$s is the title of the field
         */
        $this->print_header_row( '<th class="%2$s" scope="col">%1$s</th>' );
        ?>
      </tr>
    </thead>
    <?php // print the table footer row if there is a long list
      if ( $records_per_page > 30 ) : ?>
    <tfoot>
      <tr>
        <?php $this->print_header_row( '<th class="%2$s" scope="col">%1$s</th>' ) ?>
      </tr>
    </tfoot>
    <?php endif ?>

    <tbody>
    <?php while ( $this->have_records() ) : $this->the_record(); // each record is one row ?>
      <tr>
        <?php while( $this->have_fields() ) : $this->the_field(); // each field is one cell ?>

          <?php $value = $this->field->value;
          if ( ! $this->field->is_empty( $value ) ) : ?>
          <td>
          	<?php 
						// wrap the item in a link if it's enabled for this field
						if ( $this->field->is_single_record_link() ) {
              echo Participants_Db::make_link(
                $single_record_link,             // URL of the single record page
                $value,                          // field value
                '<a href="%1$s" title="%2$s" >', // template for building the link
                array( 'pdb'=>$this->field->record_id )    // record ID to get the record
              );
            } ?>

            <?php /*
            * here is where we determine how each field value is presented,
            * depending on what kind of field it is
            */
            switch ( $this->field->form_element ) :

							case 'image-upload': 
                
                $image = new PDb_Image(array('filename' => $value));
                $image->print_image();
                break;
								
							case 'date':
							
								/*
								 * if you want to specify a format, include it as the second 
								 * argument in this function; otherwise, it will default to 
								 * the site setting
								 */
								$this->show_date( $value, false );
								
								break;
								
						case 'multi-select-other':
						case 'multi-checkbox':
						
							/*
							 * this function shows the values as a comma separated list
							 * you can customize the glue that joins the array elements
							 */
							$this->show_array( $value, $glue = ', ' );
							
							break;
							
						case 'link' :
							
							/*
							 * prints a link (anchor tag with link text)
							 * for the template:
							 * %1$s is the URL
							 * %2$s is the linked text
							 */
							$this->show_link( $value, $template = '<a href="%1$s" >%2$s</a>', true );
							
							break;
							
						case 'rich-text':
						case 'text-area':
							
							/*
							 * if you are displaying rich text you may want to process the 
							 * output through wpautop like this: wpautop( $value ) see 
							 * http://codex.wordpress.org/Function_Reference/wpautop
							 */
							printf(
										 '<span class="%s">%s</span>',
										 $this->field->form_element == 'rich-text' ? 'textarea richtext' : 'textarea',
										 $value
										 );
							
              break;
							
						case 'text-line':
						default:
						
							/*
							 * if the make links setting is enabled, try to make a link out of the field
							 */
							if ( $this->options['make_links'] && ! $this->field->is_single_record_link() ) {
								
								$this->show_link( $value, $template = '<a href="%1$s" >%2$s</a>', true );
								
							} else {
								
								echo esc_html( $value );
								
							}

            endswitch; // switch by field type ?>
            <?php // close the anchor tag if it's a link 
						if ( $this->field->is_single_record_link() ) : ?>
            	</a>
            <?php endif ?>
            </td>
            
        <?php else : // if the field is empty ?>
        <td></td>
        <?php endif ?>
        
			<?php endwhile; // fields ?>
      </tr>
    <?php endwhile; // records ?>
    </tbody>
    
    <?php else : // if there are no records ?>

    <tbody>
      <tr>
        <td><?php _e('No records found', 'participants-database' )?></td>
      </tr>
    </tbody>

    <?php endif; // $record_count > 0 ?>
    
	</table>
  <?php
  /* END LIST */
  
  /* PAGINATION
   * 
   * this is how you customize the pagination controls
   *
   * the properties array can have these values (omit to use default value):
	 *                'current_page_class' the class name for the current page link
	 *                'disabled_class'     the class to apply to disabled links
	 *                'anchor_wrap'        whether to wrap the disabled link in an 'a' tag
	 *                'first_last'         whether to show the first and last page links
	 *                'wrappers'           array of values with the HTML to wrap the links in:
	 *                                            'wrap_tag'            tag name of the outside wrapper
	 *                                            'wrap_class'          class of the outside wrapper
	 *                                            'all_button_wrap_tag' the tag name to wrap the buttons
	 *                                            'button_wrap_tag'     the tag name to use for each button
	 *
	 *                                            
   */
	$this->pagination->set_props(array(
																		 'first_last' => false,
																		 'current_page_class'=>'currentpage',
																		 'wrappers' => array(
																												'wrap_tag' => 'div',
																												'wrap_class' => 'pagination',
																												'all_button_wrap_tag' => 'ul',
																												'button_wrap_tag' => 'li',
																												)
																		 ));
	$this->pagination->show();?>
</div>