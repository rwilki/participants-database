<?php
/*
 * class providing CSV file import functionality
 *
 * @package    WordPress
 * @subpackage Participants Database Plugin
 * @author     Roland Barker <webdesign@xnau.com>
 * @copyright  2012 xnau webdesign
 * @license    GPL2
 * @version    0.1
 * @link       http://xnau.com/wordpress-plugins/
 * @depends    parseCSV class
 *
 * this class is given a $_POST field name for an uploaded file and an optional
 * list of target columns. it parses the file contents as a CSV-format text file
 * and imports the records, matching the fields with the provided columns. Each
 * line of the CSV can be read from the object for storage or other use.
 *
 */
abstract class CSV_Import {
  
  // array of all the valid column names in the receiving database
  var $column_names;
  var $column_count;
  
  // holds the system path to the web root
  var $root_path;
  
  // holds the path to the target location for the uploaded file 
  var $upload_directory;
  
  // holds the name of the $_POST element with the uploaded file name
  var $file_field_name;
  
  // holds any errors or confirmation messages
  var $errors;
  // status of the error message
  var $error_status = 'updated';
  
  // holds the number of inserted, skipped or updated records
  var $insert_count = 0;
  var $update_count = 0;
  var $skip_count = 0;
  
  // holds the context string for the internationalization functions
  var $i10n_context;
  
  // the parseCSV instance
  var $CSV;
  
  function __construct( $file_field ) {
    
    $this->_set_root_path();
    
    if ( isset( $_POST[$file_field] ) ) {
      
      if ( $this->_set_upload_dir() ) {
        
        $target_path = $this->root_path.$this->upload_directory . basename( $_FILES['uploadedfile']['name']);

        if( false !== move_uploaded_file( $_FILES['uploadedfile']['tmp_name'], $target_path ) ) {
      
          $this->set_error( sprintf( __('The file %s has been uploaded.', $this->i10n_context ), $_FILES['uploadedfile']['name'] ), false );
          
          $this->insert_from_csv( $target_path );
      
          if ( $this->insert_count > 0 ) {
          
            $this->set_error_heading( $this->insert_count.' '._n('record added','records added', $this->insert_count, $this->i10n_context ), '', false );
            
          }
          if ( $this->update_count > 0 ) {
          
            $this->set_error_heading( $this->update_count.' '._n('matching record updated','matching records updated', $this->insert_count, $this->i10n_context ), '', false );
            
          }
          
          if ( $this->skip_count > 0 ) {
          
            $this->set_error_heading( $this->skip_count.' '._n('duplicate record skipped','duplicate records skipped', $this->insert_count, $this->i10n_context ), '', false );
            
          }
          if ( $this->update_count == 0 and $this->insert_count == 0 ) {
            
            $this->set_error_heading( __('Zero records imported', $this->i10n_context ) );
            
          }
          
        } // file move successful
        
        else { // file move failed
      
            $this->set_error_heading(
                                     __('There was an error uploading the file. This could be a problem with permissions on the uploads directory.', $this->i10n_context ),
                                     __('Destination', $this->i10n_context ).': '.$target_path
                                     );
      
        }
        
      } else {
        
        $this->set_error_heading(
                                 __('Target directory does not exist and could not be created. Try creating it manually.', $this->i10n_context ),
                                 __('Destination', $this->i10n_context ).': '. $upload_location
                                 );
        
      }
      
    }
    
  }
  
  /**
   * sets up the column name array
   *
   * @param array $array an indexed array of string field names
   *
   */
  abstract protected function _set_column_array();
  
  /**
   * sets and verifies the uploads directory
   *
   * @return bool true if the directory can be used
   */
  abstract protected function _set_upload_dir();
  
  /**
   * stores the record in the database
   *
   */
  abstract protected function store_record( $array );
  
  /**
   * sets up the root path for the uploaded file
   *
   * defaults to the WP root
   */
  function _set_root_path() {
    
    $this->root_path = ABSPATH;
    
  }
  
  /**
	 * inserts a series of records from a csv file
	 *
	 * @param string $src_file the file to parse
	 *
	 * @return bool success/failure
	 * 
	 */
	protected function insert_from_csv( $src_file ) {
	
		global $wpdb;
		$wpdb->hide_errors();
		
		$errorMsg = '';
	
		if( empty( $src_file ) || ! is_file( $src_file ) ) {

      /* translators: the %s will be the name of the file */
			$this->set_error_heading(
                               __('Error occured while trying to add the data to the database', $this->i10n_context ),
                               sprintf( __('Input file does not exist or path is incorrect.<br />Attempted to load: %s', $this->i10n_context ), basename($src_file) )
                               );
      
      return false;
	
		}
		
		$this->CSV = new parseCSV();
		
		$this->CSV->enclosure = $this->_detect_enclosure( $src_file );
	
		$this->CSV->auto( $src_file );
	
		// build the column names from the CSV if it's there
		$this->import_columns();
		
		foreach ( $this->CSV->data as $csv_line ) {
	
			 //error_log( __METHOD__.' csv line= '.print_r( $csv_line, true ) );
			
			$values = array();
			
			foreach( $csv_line as $value ) {
        
				$values[] = $wpdb->escape( $this->_enclosure_trim( $value, '', $this->CSV->enclosure ) );
			
      }
	
			if ( count( $values ) != $this->column_count ) {
	
				$this->set_error( sprintf(
                                  __('The number of items in line %s is incorrect.<br />There are %s and there should be %s.', $this->i10n_context ),
                                  $line_num, 
                                  count( $values ), 
                                  $this->column_count
                                  )
        );
        
        return false;
	
			}
			
      // put the keys and the values together into the $post array
			if ( ! $post = array_combine( $this->column_names, $values ) )
        $this->set_error( __('Number of values does not match number of columns', $this->i10n_context ) );
			
      // store the record
			$this->store_record( $post );
			
		}
    
		return true;
  
	}
	
	/**
	 * trims enclosure characters from the csv field
	 *
	 * @param string $value raw value from CSV file; passed by reference
	 * @param string $key column key
	 * @param string $enclosure the enclosure character
	 * 
	 * @access public because PHP callback uses it
	 * @return string the trimmed value
	 */
	public function _enclosure_trim( &$value, $key, $enclosure ) {
    
    $enclosure = preg_quote( $enclosure );
    
    $value = preg_replace( "#^($enclosure?)(.*)\\1$#",'$2',$value );
    
    return $value;

	}
	
	/**
	 * detect an enclosure character
	 *
	 * there's no way to do this 100%, so we will look and fall back to a
	 * reasonable assumption if we don't see a clear choice: simply whichever
	 * of the two most common enclosure characters is more numerous is returned
	 *
	 * this could also be done with a regex using a backreference counting
	 * repetitions of first and last character matches
	 *
	 * @param string $csv_file path to a csv file to read and analyze
	 * @return string the best guess enclosure character
	 */
	protected function _detect_enclosure( $csv_file ) {
		
		$csv_text = file_get_contents( $csv_file );
		
		$single_quotes = substr_count( $csv_text, "'" );
		$double_quotes = substr_count( $csv_text, '"' );
		
		return $single_quotes >= $double_quotes ? "'" : '"';
		
	}
  
  /**
   * takes a raw title row from the CSV and sets the column names array with it
   * if the imported row is different from the plugin's defined CSV columns
   *
   */
  protected function import_columns() {
	
		// build the column names from the CSV if it's there
		if ( is_array( $this->CSV->titles ) and $this->column_names != $this->CSV->titles ) {
        
      $this->column_names = $this->CSV->titles;
      
      $this->errors[] = __('New columns imported from the CSV file.', $this->i10n_context );
    
      // remove enclosure characters
      array_walk( $this->column_names, array( $this, '_enclosure_trim' ), $this->CSV->enclosure );
      
      $this->column_count = count( $this->column_names );
			
		}
    
  }
  
  /**
   * adds an error to the errors array
   *
   * @param string $message      the message to add
   * @param bool   $error_status true for error, false for non-error message
   * 
   */
  protected function set_error( $message, $error_status = true ) {
    
    if ( ! empty( $message ) ) $this->errors[] = $message;
    if ( $error_status ) $this->error_status = 'error';
    
  }
  
  /**
   * adds an error message with a header
   *
   * @param string $heading      the heading message to show
   * @param string $message      the message body (if any)
   * @param bool   $error_status true for error, false for non-error message
   */
  protected function set_error_heading( $heading, $message = '', $error_status = true ) {
          
    $this->errors[] = '<strong>'.$heading.'</strong>';
    $this->set_error( $message, $error_status );
    
  }
  
}