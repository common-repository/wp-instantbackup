<?php
/*
Plugin Name: WP-InstantBackup
Plugin URI: http://blog.cyberkai.com/?page_id=20
Description: WP-InstantBackup makes it simple to perform database and/or directory backups via FTP, Email, or both. Optional URL Instant backup allows you to make a secret key and perform a backup remotely by visiting a URL containing your secret key. All backups are zipped and can optionally be password protected. Custom File name allows you to specify the prefix of the output zip file, and we automatically append the date, time, and a random string for extra security.
Author: Andrew Forster & Nick Young
Version: 0.2.1
Author URI: http://www.cyberkai.com
*/
class WPInstantBackup
{   /* Plugin Properties */
    var $version            = "0.2.1";
    var $previousVersion    = false;
    var $versionHistory     = false;
    var $optionPrefix       = 'wp_instant_backup';
    var $errors             = 0;             
    var $pluginDir          = '';
    var $currentUser        = false;
    var $tempBackupDir      = "../WP-InstantBackup_TempBackupDir/"; // must contain the word temp
    var $wordperssRootDir   = '../';
    var $rootBackupKeyword  = '*.*';
                     
    /* Plugin Methods */
    function WPInstantBackup(){
        // set plugin directory
        $this->pluginDir = plugins_url('', __FILE__).'/';
        
        //delete_option($this->optionID('versionHistory'));
        $this->versionHistory   = $this->option('versionHistory');
        $this->previousVersion  = false;
        // if no previous version of wp-instant-backup has been recorded
        if(!$this->versionHistory || $this->versionHistory==''){
            // set the previous version to this version
            add_option($this->optionID('versionHistory'), $this->version);
            $this->versionHistory = array($this->version);        
        }
        // if we do have a version history, check if this plugins version has been recorded
        else{ 
            // if the current plugin's version cannot be found within the version history, record it
            if(!strstr($this->versionHistory,$this->version)){
                $this->versionHistory   = $this->option('versionHistory');
                $this->versionHistory  .= '|'.$this->version;
                update_option($this->optionID('versionHistory'), $this->versionHistory);           
            }
            // create our version history array 
            $this->versionHistory = explode('|', $this->versionHistory);
            for($i=0; $i<count($this->versionHistory);$i++){
                $version = trim($this->versionHistory[$i]); 
                if($this->version==$version){
                    $this->previousVersion = trim($this->versionHistory[$i-1]);
                }                
            }             
        }  
        // if we arn't within wp-admin, set wordpress root dir to blank
        if(!eregi('wp-admin', $_SERVER['REQUEST_URI'])){
            $this->wordperssRootDir = '';   
        }
        // create plugin menu in the admin
        add_action('admin_menu', array($this, 'createMenu'));
        // show the instant backup nav menu in the admin header
        add_action('in_admin_header', array($this, 'backupNavMenu'));
        // show the instant backup nav menu in the admin header
        add_action('init', array($this, 'doURLInstantBackup'));
        // add a shortcode to display the nav menu
        add_shortcode( 'wpinstantbackup_show_nav_menu', array($this, 'backupNavMenu') );
    }
    function createMenu() {             
        // only administrator allowed
        if(!$this->isAdministrator()){return;}
        // do options migration
        $this->doOptionsMigration();
        //create new top-level menu                                                                                                                                                   
        $this->instantBackupSettingsPage = add_menu_page('instant-backup-settings', 'InstantBackup', 'administrator', 'wp-instant-backup-settings', array($this, 'instantBackupSettingsPage'),plugins_url('/icon.png', __FILE__));
        // add menu items
        /*
        add_submenu_page( 'wp-instant-backup-settings', 'instant-backup-do-db-backup', 'Backup Database', 'administrator', 'wp-instant-backup-do-db-backup', array($this, 'doDBBackup') );
        add_submenu_page( 'wp-instant-backup-settings', 'instant-backup-do-file-backup', 'Backup Filesystem', 'administrator', 'wp-instant-backup-do-file-backup', array($this, 'doFileBackup') );
        add_submenu_page( 'wp-instant-backup-settings', 'instant-backup-do-full-backup', 'Backup Both', 'administrator', 'wp-instant-backup-do-full-backup', array($this, 'doFullBackup') );
        */
        // call register settings function which registers settings for the plugin page
        add_action( 'admin_init', array($this, 'register_mysettings'));
        // add meta boxes
        add_meta_box('instant-backup-ftp-settings', __('FTP Backup Settings'), 
        array($this, 'ftpSettingsMetaBox'), 'instant-backup-meta-box-settings', 'right');
        add_meta_box('instant-backup-email-settings', __('Email Backup Settings'), 
        array($this, 'emailSettingsMetaBox'), 'instant-backup-meta-box-settings', 'right');
        add_meta_box('instant-backup-backup-output-settings', __('Backup Output Settings'), 
        array($this, 'backupOutputSettingsMetaBox'), 'instant-backup-meta-box-settings', 'left');
        add_meta_box('instant-backup-backup-selection-list', __('Backup Selection List'), 
        array($this, 'backupSelectionListMetaBox'), 'instant-backup-meta-box-settings', 'left');
        add_meta_box('instant-backup-remote-backup-settings', __('URL Instant Backup Settings'), 
        array($this, 'remoteBackupSettingsMetaBox'), 'instant-backup-meta-box-settings', 'left');
        
        
        
        
        
        // print scripts for this plugin               
        add_action("admin_print_scripts-".$this->instantBackupSettingsPage, array($this,'loadScripts')); 
    }
    function loadScripts(){ 
        wp_enqueue_script('post');      
    }
    function register_mysettings() {
        //register our settings
        register_setting( 'instant-backup-settings', $this->optionID('zip_file_password' )); 
        register_setting( 'instant-backup-settings', $this->optionID('db_backup_current' )); 
        register_setting( 'instant-backup-settings', $this->optionID('backup_filename' )); 
        register_setting( 'instant-backup-settings', $this->optionID('backup_dir' )); 
        register_setting( 'instant-backup-settings', $this->optionID('date_format')); 
        register_setting( 'instant-backup-settings', $this->optionID('email_to' )); 
        register_setting( 'instant-backup-settings', $this->optionID('email_from' )); 
        register_setting( 'instant-backup-settings', $this->optionID('subject' )); 
        register_setting( 'instant-backup-settings', $this->optionID('email_message' )); 
        register_setting( 'instant-backup-settings', $this->optionID('ftp_host' )); 
        register_setting( 'instant-backup-settings', $this->optionID('ftp_user' )); 
        register_setting( 'instant-backup-settings', $this->optionID('ftp_pass' ));                
        register_setting( 'instant-backup-settings', $this->optionID('enable_ftp_backup' )); 
        register_setting( 'instant-backup-settings', $this->optionID('enable_email_backup' )); 
        register_setting( 'instant-backup-settings', $this->optionID('enable_url_instant_backup' )); 
        register_setting( 'instant-backup-settings', $this->optionID('secret_key' )); 
    }
    /* Settings Meta Boxes */
    function ftpSettingsMetaBox(){
        $host = $this->option('ftp_host');
        $user = $this->option('ftp_user');
        $pass = $this->option('ftp_pass');
    ?>              
     <table class="form-table">
        <tr valign="top">
        <th scope="row" colspan="2">FTP Backup will allow you to perform a Full Database Backup along with a backup of the files and folders specified in the Backup Selection List if specified.  FTP is normally the best way to do a backup.
        </th>
        </tr>
        
        
        
        
        <tr valign="top">
        <th scope="row">Enable FTP Backup Method</th>
        <td>
        <?PHP
        $enableFTPBackup    = $this->option('enable_ftp_backup')?$this->option('enable_ftp_backup'):false;
        $checked            = '';
        if($enableFTPBackup=="on"){ $checked = 'checked="checked"'; }
        ?>
        <input type="checkbox" name="<?PHP echo $this->optionID('enable_ftp_backup'); ?>" <?php echo $checked; ?> /></td>
        </tr>
             
        <tr valign="top">
        <th scope="row">Host</th>
        <td><input type="text" name="<?PHP echo $this->optionID('ftp_host'); ?>" value="<?php echo $host; ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Username</th>
        <td><input type="text" name="<?PHP echo $this->optionID('ftp_user'); ?>" value="<?php echo $user; ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Password</th>
        <td><input type="text" name="<?PHP echo $this->optionID('ftp_pass'); ?>" value="<?php echo $pass; ?>" /></td>
        </tr>
        
        <?PHP
        // test ftp connection if enable ftp is on 
        if($enableFTPBackup){ 
            $ftpTest = $this->testFTPConnection($host, $user, $pass);
            if($ftpTest){
            ?>
            <tr valign="top">     
            <td colspan="2" class="success">FTP Settings OK!</td>
            </tr>
            <?PHP } else { ?>
            <tr valign="top">
            <td></td>             
            <td colspan="2" class="error">FTP Settings invalid!</td>
            </tr>
        <?PHP }} 
        ?>
        
    </table>
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) { 
                 postboxes.add_postbox_toggles('instant-backup-meta-box-database-settings');
            });
        //]]>
    </script>    
    <?PHP    
    }
    function emailSettingsMetaBox(){
    ?>                                      
    <table class="form-table">
    
        <tr valign="top">
        <th scope="row" colspan="2">Email Backup will allow you to perform a Full Database Backup.  (Note: This ONLY works for database at the moment.)</th>
        </tr>
        
        <tr valign="top">
        <th scope="row">Enable Email Backup Method</th>
        <td>
        <?PHP
        $enableEmailBackup    = $this->option('enable_email_backup')?$this->option('enable_email_backup'):false;
        $checked                = '';
        if($enableEmailBackup=="on"){ $checked = 'checked="checked"'; }
        ?>
        <input type="checkbox" name="<?PHP echo $this->optionID('enable_email_backup'); ?>" <?php echo $checked; ?> />
        <br /><em>* Only database backups are sent via email.</em>
        </td>
        </tr>
             
        <tr valign="top">
        <th scope="row">To</th>
        <td><input type="text" name="<?PHP echo $this->optionID('email_to'); ?>" value="<?php echo $this->option('email_to'); ?>" />
        <br />* <em>(eg. johndoe@gmail.com)</em>
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">From</th>
        <td><input type="text" name="<?PHP echo $this->optionID('email_from'); ?>" value="<?php echo $this->option('email_from'); ?>" />
        <br />* <em>(eg. johndoe@gmail.com)</em>
        </td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Subject</th>
        <td><input type="text" name="<?PHP echo $this->optionID('subject'); ?>" value="<?php echo $this->option('subject'); ?>" />
        </td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Message</th>
        <td><textarea type="text" name="<?PHP echo $this->optionID('email_message'); ?>"><?php echo $this->option('email_message'); ?></textarea></td>
        </tr>
        
        <?PHP if(function_exists('mail')){ ?>
        <tr valign="top">     
        <td colspan="2" class="success">PHP's Mail Function OK!</td>
        </tr>
        <?PHP }else{ ?>
        <tr valign="top">     
        <td colspan="2" class="error">PHP's Mail Function not found!</td>
        </tr>
        <?PHP } ?>
        
    </table>
    
    
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) { 
                 postboxes.add_postbox_toggles('instant-backup-meta-box-email-settings');
            });
        //]]>
    </script>    
    <?PHP    
    }
    function remoteBackupSettingsMetaBox(){
    ?>                                      
    <table class="form-table">
        
        <tr valign="top">
        <th scope="row" colspan="2">With URL Instant Backup you can perform a backup remotely from anywhere via another computer or any mobile device by visiting a URL.</th>
        </tr>
        
        <tr valign="top">
        <th scope="row">Enable URL Instant Backup</th>
        <td>
        <?PHP
        $enableRemoteBackup    = $this->option('enable_url_instant_backup')?$this->option('enable_url_instant_backup'):false;
        $checked                = '';
        if($enableRemoteBackup=="on"){ $checked = 'checked="checked"'; }
        ?>
        <input type="checkbox" name="<?PHP echo $this->optionID('enable_url_instant_backup'); ?>" <?php echo $checked; ?> /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Secret Key</th>
        <td><input type="text" name="<?PHP echo $this->optionID('secr'); ?>et_key" value="<?php echo $this->option('secret_key'); ?>" />
        <br /> * <em>Enter your secret key then click <br />save settings to generate a secure <br />URL for each backup type.</em>
        </td>
        </tr>
        
        <?PHP
        $secretKey = $this->option('secret_key');
        if($secretKey && $secretKey){
        ?>
        
        <tr valign="top">
        <th scope="row">Database Backup</th>         
        <td colspan="2"><input type="text" readonly="readonly" value="<?PHP echo get_bloginfo('siteurl'); ?>/?doURLInstantBackup=true&backupType=db&secretkey=<?PHP echo $this->getHashedSecretKey($secretKey); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Filesystem Backup</th>         
        <td colspan="2"><input type="text" readonly="readonly" value="<?PHP echo get_bloginfo('siteurl'); ?>/?doURLInstantBackup=true&backupType=file&secretkey=<?PHP echo $this->getHashedSecretKey($secretKey); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Full Backup</th>         
        <td colspan="2"><input type="text" readonly="readonly" value="<?PHP echo get_bloginfo('siteurl'); ?>/?doURLInstantBackup=true&backupType=full&secretkey=<?PHP echo $this->getHashedSecretKey($secretKey); ?>" /></td>
        </tr>
        <?PHP
        }
        ?>  
    </table> 
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) { 
                 postboxes.add_postbox_toggles('instant-backup-remote-backup-settings');
            });
        //]]>
    </script>    
    <?PHP    
    }
    function backupOutputSettingsMetaBox(){
        $backupFilename = $this->option('backup_filename');
        if(!$backupFilename || $backupFilename==''){
            // set default backup filename
            $backupFilename = eregi_replace('\.', '-', strtolower(get_bloginfo('siteurl')));        
            $backupFilename = eregi_replace('http://', '', $backupFilename);        
        }
    ?>
    <table class="form-table">
           
        <tr valign="top">
        <th scope="row">Backup Filename *</th>
        <td><input type="text" name="<?PHP echo $this->optionID('backup_filename'); ?>" value="<?php echo $backupFilename; ?>" />
        <br /> * <em>DO NOT USE SPACES!</em>
        <br /> * <em>Date & Time will be appended to your filename.</em>
        </td>
        </tr>
        <!--  
        <tr valign="top">
        <th scope="row">Date Format</th>
        <td><input type="text" name="<?PHP echo $this->optionID('date_format'); ?>" value="<?php echo $this->option('date_format'); ?>" />
        <br /> * <em>Appended onto the backup filename. 
        <br /> * <em>(eg. Y_m_d_H_i_s) 
        <a href="http://php.net/manual/en/function.date.php" title="View Formatting Options" target="_blank">View Formatting Options</a></em></em>
        </td>
        </tr>
        -->
        
        <tr valign="top">
        <th scope="row">Zip File Password</th>
        <td><input type="text" name="<?PHP echo $this->optionID('zip_file_password'); ?>" value="<?php echo $this->option('zip_file_password'); ?>" />
        <br /> * <em>Password protects the backup zip file.</em>
        </td>
        </tr>    
    
    </table> 
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) { 
                 postboxes.add_postbox_toggles('instant-backup-backup-output-settings');
            });
        //]]>
    </script>
    <?PHP   
    }
    function backupSelectionListMetaBox(){
        $backupDir  = $this->option('backup_dir');
    ?>
    <table class="form-table">                

        <tr valign="top">
        <th colspan="2" scope="row">The Backup Selection List is used to define what files and folders should be backed up, relative to the WordPress root (specify each on a separate line).
        <br /><br />
        * <em>eg. wp-content/uploads</em>
        <br /><br />
        <textarea name="<?PHP echo $this->optionID('backup_dir'); ?>"><?php echo $backupDir; ?></textarea> 
        <br /><br /><em>Type <span style="font-size:14px;background:gold;font-weight:bold;"><?PHP echo $this->rootBackupKeyword; ?></span> to backup the entire wordpress root.</em>
        </th>
        </tr>
        
        
        
        <?PHP
        // no files/directories specified for backup
        if(!$backupDir || $backupDir==''){
            $backupDir = false;
            ?>
            <tr valign="top"> 
            <td colspan="2" class="error">No files/folders specified for backup! <br /> Only DB backups can be performed.</td>
            </tr>
            <?PHP    
        }
        else{
            // if the rootBackupKeyword has been specified 
            if($backupDir==$this->rootBackupKeyword){
                // set $backupDir to the root
                $backupDir = '';    
            }
            $backupDirs = explode(PHP_EOL, $backupDir);
            // test that all specified directories/files are valid
            $test = $this->testBackupDirectories($backupDirs);                                     
            if($test===true)
            {
                $sizeInBytes    = $this->estimateBackupSize($backupDirs);
                $backupSize     = $this->getFilesizeIn('kb', $sizeInBytes);
                if($backupSize > 1024){
                    $backupSize = $this->getFilesizeIn('mb', $sizeInBytes);    
                    if($backupSize > 1000){
                        $backupSize = $this->getFilesizeIn('gb', $sizeInBytes);    
                        $backupSize .= ' GB'; 
                    }else{ 
                        $backupSize .= ' MB';    
                    }
                }else{ 
                    $backupSize .= ' KB';    
                }
                    
            ?>
            <tr valign="top"> 
            <td colspan="2" class="success">
                <?PHP
                // if the root directory has been marked for backup
                if($backupDir==''){
                    echo "ROOT Folder Marked For Backup! ";
                }else{
                    echo "These Files/Folders are OK!";
                }
                ?> Est. Backup Size <?PHP echo $backupSize; ?>
            </td>
            </tr>
            <?PHP 
            } else{
                // display file exists errors
                if(count($test['fileExistsErrors'])>0)
                {   
                ?>
                    <tr valign="top">
                    <td colspan="2" class="error">The following Files/Folders do not exist:
                    <br />
                    <?PHP                              
                    for($i=0; $i<count($test['fileExistsErrors']);$i++){
                        echo ' - '.$test['fileExistsErrors'][$i].'<br />';
                    }
                    ?>
                    </td>
                    </tr>
                <?PHP
                }
                // display file permissions errors
                if(count($test['filePermissionsErrors'])>0)
                {
                ?>
                    <tr valign="top">
                    <td colspan="2" class="error">The following Files/Folders do not have enough permissions:
                    <br />
                    <?PHP                                    
                    for($i=0; $i<count($test['filePermissionsErrors']);$i++){
                        echo ' - '.$test['filePermissionsErrors'][$i].'<br />';
                    }          
                    ?>
                    </td>
                    </tr>
                <?PHP
                } 
            }
        }
        ?>
    </table> 
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) { 
                 postboxes.add_postbox_toggles('instant-backup-backup-selection-list');
            });
        //]]>
    </script>
    <?PHP   
    }
    /* Settings Page */
    function instantBackupSettingsPage(){ 
        // only administrator allowed
        if(!$this->isAdministrator()){return;}
        
        $settingsUpdated    = $_GET['settings-updated'];
        $doBackup           = $_GET['doBackup'];
        // check if we should perform a backup
        if($doBackup=='true'){
            // ensure that a backup type has been set 
            $backupType     = $_GET['backupType'];
            if(!$backupType){
                $this->error("Error: A backup type must be set.");
            }else{
                $this->doBackup($backupType);
            }   
        } 
        // manually set refferrer to always point to this page with no other query string variables
        // this helps to avoid backups from running when settings are being saved!
        $_SERVER['REQUEST_URI'] = 'admin.php?page='.$_GET['page'];
        ?>
        <style>
        .left{width:49%;}
        .right{width:49%;}
        h3{padding:10px;margin:0px;} 
        .wrap .version {font-size:12px;font-weight:bold;}
        .wrap .icon {float:left;padding-top:23px;}
        .postbox {min-width:75%;}                                     
        .postbox textarea{width:100%;min-height:150px;}
        .postbox input[type="text"]{width:100%;border-color:#CCCCCC;}
        .form-table th{width:125px;color:#585858;font-size:12px;border-bottom:1px solid #cccccc;line-height:18px;}
        .form-table td{border-bottom:1px solid #cccccc;line-height:18px;}
        .form-table td.success{color:#555555;background:#C2FF7F;font-weight:bold;padding:2px 10px;}
        .form-table td.error{color:white;background:#FF4F56;font-weight:bold;padding:2px 10px;}
        .form-table em{color:black;}
        </style>
        <div class="wrap">
        
        <?PHP
        // if settings have been updated, show success 
        if($settingsUpdated=="true"){ 
            $this->success("Settings Saved Successfully");
        } 
        ?>
        <div style="text-align:center;">
            <img src="<?PHP echo $this->pluginDir; ?>logo.png" />
            <div class="version">Version <?PHP echo $this->version; ?></div>
            <?PHP
            $lastBackupTimestamp = $this->option('date_last_backed_up_timestamp');
            // show date last backed up
            if($lastBackupTimestamp){
            $lastBackup = date('D F d, Y @ g:i A', $lastBackupTimestamp);
            ?> 
            <h3>Last backup was on <?PHP echo $lastBackup; ?></h3> 
            <?PHP } ?>
        </div>
        
        <center> 
        <form method="post" action="options.php">
            <?php 
            wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false );
            wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false );
            settings_fields( 'instant-backup-settings' );
            do_settings_sections('instant-backup-settings');
            ?> 
            
            
            
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
            </p> 
            
            <table>
            <tr>
                <td class="left" valign="top"><?PHP do_meta_boxes('instant-backup-meta-box-settings', 'left', null); ?></td>
                <td>&nbsp;</td>
                <td class="right" valign="top"><?PHP do_meta_boxes('instant-backup-meta-box-settings', 'right', null); ?></td>
            </tr>
            </table>
                     
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
            </p>
        </form>
        </center>
        </div>
        <?PHP
    }
    /* Backup Nav Menu */ 
    function backupNavMenu(){
        // only administrator allowed
        if(!$this->isAdministrator()){return;}
    ?>
    <div style="float:right;padding:14px;border-left:1px solid #aaaaaa;margin-left:14px;">
        Backup
        <span style="font-weight:bold;font-size:14px;">
            &nbsp; <a onclick="doBackup('db', 'Database');" 
            title="Perform a Database Backup" style="cursor:pointer;">DB</a>
            &nbsp; <a onclick="doBackup('file', 'Filesystem');" 
            title="Perform a Filesystem Backup" style="cursor:pointer;">FS</a>
            &nbsp; <a onclick="doBackup('full', 'Full');" 
            title="Perform a Full Backup" style="cursor:pointer;">FULL</a>
        </span>
    </div>
    <script type="text/javascript">
    function doBackup(backupType, label){
        if(confirm('Perform a '+label+' Backup?')){
            location.href="admin.php?page=wp-instant-backup-settings&doBackup=true&backupType="+backupType;
        }    
    }
    </script>
    <?PHP            
    }
    /* DO THE BACKUP */ 
    function doBackup($backupType){                     
        $now                        = date('D F d, Y @ g:i A');                                          
        $dbBackupZipFileCreated     = true;
        $fileBackupZipFileCreated   = true;
        $randomString               = strtolower($this->getRandomAlphaString(5));
        
        // set the $dateString used in each backups filename  
        $dateFormat                 = $this->option('date_format');         
        $dateFormat                 = eregi_replace(' ', '', $dateFormat);   
        if(!$dateFormat || $dateFormat==''){    
            $dateFormat             = 'Y-m-d_Hi-s';
        }    
        $dateString                 = date($dateFormat, time());
        
        $rootFilepath               = $this->wordperssRootDir;
        $zipFilePrefix              = $this->option('backup_filename');
        $zipFileSuffix              = $dateString.'_r'.$randomString;
        
        $dbBackupSQLFilename        = $zipFilePrefix.'_DB_'.$zipFileSuffix.".sql"; 
        $dbBackupZipFilename        = $zipFilePrefix.'_DB_'.$zipFileSuffix.".zip"; 
        $fileBackupZipFilename      = $zipFilePrefix.'_FS_'.$zipFileSuffix.".zip";
        $tempBackupDir              = $rootFilepath.$zipFilePrefix.'_FS_'.$zipFileSuffix.'/';
        
        $dbBackupSQLFilepath        = $rootFilepath.$dbBackupSQLFilename; 
        $dbBackupZipFilepath        = $rootFilepath.$dbBackupZipFilename; 
        $fileBackupZipFilepath      = $rootFilepath.$fileBackupZipFilename;
                                                      
        $backupDir                  = $this->option('backup_dir');
         
        $host                       = $this->option('ftp_host');
        $user                       = $this->option('ftp_user');
        $pass                       = $this->option('ftp_pass');         
        
        $passwordCommand            = '';
        $zipFilePassword            = $this->option('zip_file_password');
        if($zipFilePassword && $zipFilePassword!=''){ 
            $passwordCommand = '-P '.$zipFilePassword; 
        } 
        
        $enableEmailBackup          = $this->option('enable_email_backup');
        $enableFTPBackup            = $this->option('enable_ftp_backup');
        
                   
        // don't perform a backup if neither email or ftp backups are enabled
        if(!$enableEmailBackup && !$enableFTPBackup){
            $this->error("Error: You must enable either email or ftp backup to perform a backup.");
            return;
        }
        // if this is a full or filesystem backup
        if(($backupType=='file' || $backupType=='full')){
            // if no directories have been specified, error out
            if(!$enableFTPBackup){ 
                $this->error('Filesystem and Full backups require the FTP Backup Method to be enabled.');
                return;
            } 
            // if no directories have been specified, error out
            if(!$backupDir){ 
                $this->error('No files/folders specified for backup! Only DB Backups can be performed.');
                return;
            }
            // if some ftp details have not been specified, error out
            if(!$host || !$user || !$pass){
                $this->error("Error: Incomplete FTP Backup Settings");
                $fileBackupZipFileCreated = false;
            }
            
            // if the rootBackupKeyword has been specified, let's backup the root 
            if($backupDir==$this->rootBackupKeyword){
                $directories = array('');  
            }
            // let's backup the specified directories
            else{ 
                $directories = explode(PHP_EOL, $backupDir);
            }                                  
            // test all backup dir's first
            $test = $this->testBackupDirectories($directories);
            if($test!==true){
                $this->error("Error: Some specified directories/files do not exist!");
                return;        
            }
               
        } 
        // test ftp parameters
        if($enableFTPBackup){
            $ftpConnection = $this->testFTPConnection($host, $user, $pass, true);
            if(!$ftpConnection){
                $this->error("Error: Invalid FTP Backup Settings");
                $fileBackupZipFileCreated = false;       
            }
        }
        // create database backup sql file and zip it up
        if($backupType=='db' || $backupType=='full'){
            // backup database
            $backupCommand    = "mysqldump -u ".DB_USER." --password=".DB_PASSWORD." ".DB_NAME." > ".$dbBackupSQLFilepath; // credentials from Database.cfg.php       
            exec($backupCommand);
            // if the database backup file we just created doesnt exist, error out 
            if(!file_exists($dbBackupSQLFilepath)){                           
                $this->error("Error: Backup sql file could not be created");
                return;
            }  
            // zip up the sql backup file
            exec("zip $passwordCommand $dbBackupZipFilepath $dbBackupSQLFilepath");
            // if the db bakcup zip file we just created doesnt exist, error out 
            if(!file_exists($dbBackupZipFilepath)){                           
                $this->error("Error: DB backup zip file could not be created");
                $dbBackupZipFileCreated = false;
            } 
        }         
        // backup filesystem
        if(($backupType=='file' || $backupType=='full') && $enableFTPBackup){
            // check
            if($tempBackupDir && $tempBackupDir!=''){  
                // create temporary backup directory but clean it out first
                @rmdir ($tempBackupDir);
                mkdir($tempBackupDir, 0777);    
                // only 1 directory/file to backup  
                if(count($directories)==1){ 
                    $directory = trim($directories[0]);
                    $directory = eregi_replace('^/', '', $directory);
                    // set zip filename to root if a directory name has not been specified eg. ../
                    if($directory == '' || eregi('^[^a-zA-Z0-9]+$', $directory)){
                        $zipFilename = 'WebsiteRoot';
                    }else{
                        $zipFilename = $this->getCurrentDirFromFilepath($directory);    
                    }                
                    // create zip  
                    $source         = $rootFilepath.$directory;
                    $destination    = $tempBackupDir.$zipFilename.'.zip';
                    //echo $source. '<br />'.$destination;
                    // if the directory backup zip file we just created doesnt exist, error out 
                    if(!$this->createZipFile($source, $destination)){                           
                        $this->error("Error: Filesystem backup zip file could not be created.");
                        $fileBackupZipFileCreated = false;
                    }  
                }  
                // multiple directories/files to backup  
                else if(count($directories)>1){
                    // now move all other directories/files into the backup directory
                    foreach($directories as $directory){
                        // trim all whitespace and remove any trailing slashes from filepath
                        $directory = trim($directory);
                        $directory = eregi_replace('^/', '', $directory);
                        
                        // if this is a directory and not a specific file
                        if(!eregi('\.',$directory)){  
                            // zip it up to the temp backup directory
                            $source = "../$directory";
                            $destination = $tempBackupDir.$this->getRootDirFromFilepath($directory).'.zip';
                            //echo $source.' | '.$destination;
                            $this->createZipFile($source, $destination); 
                        }
                        // if it's a file, then let's just copy it
                        else{
                            $filename   = $this->getFilenameFromFilepath($directory);
                            //$directory  = $this->getDirectoryFromFilepath($directory);                                
                            copy('../'.$directory, $tempBackupDir.$filename);    
                        }              
                        /*
                        // temp
                        // temp/
                        // temp/temp/
                        // temp.php
                        // temp/temp.php
                        $directoryName = explode("/", $directory);
                        $directoryName = $directoryName[count($directoryName)-1];
                        // get the directory/file name
                        $lastIndex = count($directoryName)-1;
                        if(eregi('/$', $directoryName)){
                            $lastIndex = count($directoryName)-2;        
                        }
                        $directoryName = $directoryName[$lastIndex];
                        // remove the file extension if their is one
                        if(eregi('\.', $directoryName)){
                            $directoryName = eregi_replace('\.[.]*', '', $directoryName);        
                        } 
                        // convert spaces and hyphens to underscores
                        $directoryName = eregi_replace('\s',"", $directoryName);
                        $directoryName = eregi_replace('-',"", $directoryName);
                        // zip up the directory and place it within the temp backup dir
                        // $fileBackupZipFilepath = '../'.$directoryName.'_'.$zipFileSuffix.'.zip';
                        // exec("zip -r $passwordCommand $fileBackupZipFilepath $tempBackupDir");  
                        */
                    }
                }
            }
            // if all backup zip file(s) were created successfully
            if($fileBackupZipFileCreated){    
                // now time to zip the temp backup dir and remove the actual dir
                $source         = $tempBackupDir;
                $destination    = $fileBackupZipFilepath;
                // echo $source.' | '.$destination;
                // if the filesystem backup zip file we just created doesnt exist, error out 
                if(!$this->createZipFile($source, $destination, $zipFilePassword)){                           
                    $this->error("Error: Filesystem backup zip file could not be created.");
                    $fileBackupZipFileCreated = false;
                } 
                // clean out the temp backup dir
                $this->removeTempBackupDirectory($tempBackupDir, $dateString);
            } 
        }    
        // if no backup zips were created, don't try and send via email or ftp
        if(!$dbBackupZipFileCreated || !$fileBackupZipFileCreated){return;}  
        // if email backups are enabled and this is not a filesystem backup, let's send out an email backup
        if($enableEmailBackup && $backupType!='file'){
            $to             = $this->option('email_to');
            $from           = $this->option('email_from');
            $subject        = $this->option('subject')?$this->option('subject')." (".$now.")":"Instant Backup (".$now.")";
            $message        = $this->option('email_message')?$this->option('email_message'):'Backup Successful!';
            $boundaryString = md5(time());
            // required information checks
            if(!$to || !$from){
                $this->error("Error: To and From must be set under Email Backup Settings");    
            }  
            //Normal headers 
            $headers        = "From: ".$from."\r\nReply-To: ".$from."\r\n";
            $headers        .= "MIME-Version: 1.0\r\n";
            $headers        .= "Content-Type: multipart/mixed; ";
            $headers        .= "boundary=".$boundaryString."\r\n";
            $headers        .= "--$boundaryString\r\n";
            // This two steps to help avoid spam
            $headers        .= "Message-ID: <".time()." TheSystem@".$_SERVER['SERVER_NAME'].">\r\n";
            $headers        .= "X-Mailer: PHP v".phpversion()."\r\n";
            // With message
            $headers        .= "Content-Type: text/html; charset=iso-8859-1\r\n";
            $headers        .= "Content-Transfer-Encoding: 8bit\r\n\n";
            $headers        .= "".$message."\n";
            $headers        .= "--".$boundaryString."\r\n";
            // add DB Backup attachement headers 
            if($backupType=='full' || $backupType=='db'){
                $headers        .= "Content-Type:application/octet-stream";
                $headers        .= "name=\"".$subject."\"\r\n";
                $headers        .= "Content-Transfer-Encoding: base64\r\n";
                $headers        .= "Content-Disposition: attachment;";
                $headers        .= "filename=\"".$dbBackupZipFilename."\"\r\n\n";
                $headers        .= "".chunk_split(base64_encode(file_get_contents($dbBackupZipFilepath)))."\r\n";
                $headers        .= "--".$boundaryString."\r\n";
            }
            //send the email
            if(mail( $to, $subject, "Backed up", $headers)===false)
            {   
                $this->error("Error: PHP's mail function could not send the email backup!");  
            }       
        }
        // if ftp backup is enabled
        if($enableFTPBackup){
            // upload filesystem backup zip file
            if($backupType=='full' || $backupType=='file'){ 
                $upload = ftp_put($ftpConnection, $fileBackupZipFilename, $fileBackupZipFilepath, FTP_BINARY);
                if (!$upload) {    
                    $this->error("Error: Filesystem Backup could not be uploaded to FTP.");
                }
            }
            // upload db backup zip file
            if($backupType=='full' || $backupType=='db'){  
                $upload = ftp_put($ftpConnection, $dbBackupZipFilename, $dbBackupZipFilepath, FTP_BINARY);
                if (!$upload) {    
                    $this->error("Error: Filesystem Backup could not be uploaded to FTP.");
                }
            }
            // close ftp connection
            ftp_close($ftpConnection);
        }
        // if there were no errors, let's output success and record the date last backed up :)
        if($this->errors===0){
            $this->success("Backup successfully performed!");
            if ($this->option('date_last_backed_up_timestamp') != time()) {
                update_option('date_last_backed_up_timestamp', time());
            }else{
                add_option('date_last_backed_up_timestamp', time());    
            }
                
        }    
        // always remove local version of backup files
        @unlink($dbBackupSQLFilepath);
        @unlink($dbBackupZipFilepath); 
        @unlink($fileBackupZipFilepath);
    }
    function doDBBackup(){
        $_GET['doBackup']   = true;
        $_GET['backupType'] = 'db';
        $this->instantBackupSettingsPage();        
    }
    function doFileBackup(){
        $_GET['doBackup']   = true;
        $_GET['backupType'] = 'file';
        $this->instantBackupSettingsPage();      
    }
    function doFullBackup(){
        $_GET['doBackup']   = true;
        $_GET['backupType'] = 'full';
        $this->instantBackupSettingsPage();
    }
    function doURLInstantBackup(){ 
        $doURLInstantBackup = $_GET['doURLInstantBackup'];
        $backupType         = $_GET['backupType'];      
        $secretKey          = $this->getHashedSecretKey($_GET['secretKey']);
        $originalSecretKey  = $this->getHashedSecretKey($this->option('secret_key'));
        $enableURLInstantBackup = $this->option('enable_url_instant_backup');
        // if a request has been recieved to perform a URL Instant Backup
        if($doURLInstantBackup=='true'){
            // ensure that URL Instant Backup is enabled!
            if(!$enableURLInstantBackup){
                die();    
            }
            // if provided secret key matches with the original, do the backup
            if(strcmp($secretKey, $originalSecretKey)===0){
                $this->doBackup($backupType);
            }
            die(); 
        }             
    }
    function doOptionsMigration(){
        //delete_option($this->optionID('optionsMigrated'));
        $optionsMigrated = $this->option('optionsMigrated');
        // perform the migration if it hasn't been performed yet
        if(!$optionsMigrated || $optionsMigrated=='' || $optionsMigrated!=$this->version){
            $options = array(
             'zip_file_password'
            ,'db_backup_current'
            ,'backup_filename'
            ,'backup_dir'
            ,'date_format'
            ,'email_to'
            ,'email_from'
            ,'subject'
            ,'email_message'
            ,'ftp_host'
            ,'ftp_user'
            ,'ftp_pass'
            ,'enable_ftp_backup'
            ,'enable_email_backup'
            ,'enable_url_instant_backup'
            ,'secret_key'
            ,'date_last_backed_up_timestamp'
            );
            foreach($options as $option){ 
                //delete_option($this->optionID($option));     
                add_option($this->optionID($option), $this->option($option));
                //echo "replacing option $option with ".$this->optionID($option).' = '.$this->option($option).'<br />';
            }  
            add_option($this->optionID('optionsMigrated'), $this->version); 
        }   
    }
    function getHashedSecretKey(){
        $secretKey          = $this->option('secret_key');
        // create 256 character hash
        $hashedSecretKey    = md5($secretKey);
        $a                  = md5($hashedSecretKey);
        $b                  = md5($a);
        $c                  = md5($a+$b);
        $d                  = md5($a+$b+$c);
        $hashedSecretKey    = $a.$c.$b.$a.$d.$b.$c.$a;
        return($hashedSecretKey);      
    }
    function isAdministrator(){
        $this->currentUser = wp_get_current_user();
        if(array_search('administrator', $this->currentUser->roles)===false){
            return(false);
        }    
        return(true);
    }
    function testFTPConnection($host, $user, $pass, $returnConnection=false){
        $connection = @ftp_connect($host);
        $login      = @ftp_login($connection, $user, $pass);  
        if (!$connection || !$login) { 
            @ftp_close($connection);    
            return(false);
        } 
        if($returnConnection){      
            return($connection);   
        }else{
            @ftp_close($connection); 
            return(true);
        }
    }
    function testBackupDirectories($backupDirectories){
        $errors = array();   
        $errors['fileExistsErrors'] = array();        
        $errors['filePermissionsErrors'] = array();
        foreach($backupDirectories as $backupDirectory){
            $backupDirectory = trim($backupDirectory);
            if(!is_file($this->wordperssRootDir.$backupDirectory)){
                // add a trailing slash if neccesary
                if(!eregi('/$', $backupDirectory)){
                    $backupDirectory .= '/';
                }    
            }
            if(file_exists($this->wordperssRootDir.$backupDirectory)===false){
                array_push($errors['fileExistsErrors'], $backupDirectory); 
            }
            // permissions check
            /*
            $permissions = @fileperms($this->wordperssRootDir.$backupDirectory);
            //echo $permissions.'<br />';
            
            // require group write permissions, group read not neccessary      
            if($permissions && $permissions<=16848){                  
                array_push($errors['filePermissionsErrors'], $backupDirectory);
            }
            */
        }          
        if(count($errors['fileExistsErrors'])>0){ return($errors); } 
        if(count($errors['filePermissionsErrors'])>0){ return($errors); } 
        return(true);
    }
    function createZipFile($source, $destination, $password=false){
        $passwordCommand            = '';                              
        // if no source or destination provided, return false
        if(!$source || !$destination){ return(false); }
        // if we got a password, lets set the password command
        if($password && $password!=''){ 
            $passwordCommand = '-P '.$password; 
        }
                                        
        $rootDirPadding         = '';  
        $newDestination         = $destination;                           
        $changeDirectoryCommand = '';
        
        // get the filepath for the source files parent folder 
        // eg. source:          wp-content/temp/temp.php
        //     parentFilepath:  wp-content/temp/
        $parentFilepath     = $this->getParentFilepathFromFilepath($source);
                             
        // if we are zipping a specific directory
        if($parentFilepath!=''){
            // we gotta use the change directory command along with the zip command
            $changeDirectoryCommand = "cd $parentFilepath;";
            // calculate how much padding we need to reach the root directory from the 
            // parent file path
            $directoriesDeep        = $this->getDirCount($parentFilepath); 
            if($directoriesDeep>0){ 
                for($i=0;$i<$directoriesDeep;$i++){
                    $rootDirPadding .= '../';
                }
            }
            // modify the desination filepath with the new $rootDirPadding
            // !IMPORTANT as the zip file will be created relative to the $parentFilepath
            $newDestination = eregi_replace('\.\./', '', $newDestination);
            $newDestination = $rootDirPadding.$newDestination;        
            // let's get the filename or directory to be zipped from the source filepath
            // this way we should end up with either   dir/   or dir.fileExtension 
            if(is_dir($source)){
                $source     = $this->getCurrentDirFromFilepath($source);
                // append a forward slash at the end of the source filepath if a directory has been specified 
                if($source && $source!=''){
                    $source .= '/';
                }
            }
            else{ 
                $source     = $this->getFilenameFromFilepath($source);  
            }
        }
                                                 
        // create the zip file
        $output = array();                 
        $result = '';                       
        $cmd    = $changeDirectoryCommand;
        $cmd    .= "zip -r $passwordCommand $newDestination $source";                                
        //echo '<br />'.$cmd.' | '.file_exists($destination).'<br />';
        exec($cmd, $output, $result);
                  
             
        //echo $destination.' | '.$directoriesDeep.'<br />';           
        //print_r($output);
          
        if(!file_exists($destination)){                                    
            return(false);
        }return(true);         
    }
    function getFilepathFromFilepath($filepath){
        // if there is no file, just return the directory
        if(!eregi('\.', $filepath)){ return($directory); }
        // there is a file on the end of the filepath, so remove it
        $directory      = explode('/', $filepath);
        array_pop($directory);   
        $directory = implode('/', $directory);
        return($directory);      
    }
    function getRootDirFromFilepath($filepath){                   
        // removing begining and trailing slashes
        $filepath = eregi_replace('^/', '', $filepath); 
        $filepath = eregi_replace('/$', '', $filepath); 
        // get parent directory
        $parentDir      = explode('/', $filepath);
        if(count($parentDir)>1){
            $parentDir      = $parentDir[count($parentDir)-1];
            return($parentDir);
        }
        return($parentDir[0]);      
    }
    function getParentDirFromFilepath($filepath){                   
        // removing begining and trailing slashes
        $filepath = eregi_replace('^/', '', $filepath); 
        $filepath = eregi_replace('/$', '', $filepath); 
        // get parent directory
        $parentDir      = explode('/', $filepath);
        if(count($parentDir)>1){
            $parentDir      = $parentDir[count($parentDir)-2];
            return($parentDir);
        }
        return($parentDir[0]);      
    }
    function getParentFilepathFromFilepath($filepath){                   
        // removing begining and trailing slashes
        $filepath = eregi_replace('^/', '', $filepath); 
        $filepath = eregi_replace('/$', '', $filepath); 
        // get parent directory
        $parentDir      = explode('/', $filepath);
        if(count($parentDir)>1){
            
            array_pop($parentDir);
            $parentDir = implode('/', $parentDir);
            return($parentDir.'/');
        }
        return('');      
    }
    function getCurrentDirFromFilepath($filepath){
        if($filepath=='../'){return(''); }
        // removing begining and trailing slashes
        $filepath = eregi_replace('^/', '', $filepath); 
        $filepath = eregi_replace('/$', '', $filepath); 
        // get current directory
        $currentDir      = explode('/', $filepath);
        $currentDir = $currentDir[count($currentDir)-1];
        return($currentDir);      
    }
    function getFilenameFromFilepath($filepath, $removeExtension=false){
        // if there is no file, just return the directory
        if(!eregi('\.', $filepath)){ return(''); }
        // remove file extension
        if($removeExtension){
            $filepath = eregi_replace('\..*$', '', $filepath);
        }
        // there is a file on the end of the filepath, so get it
        $filepath   = explode('/', $filepath);
        $filename   = $filepath[count($filepath)-1];
        return($filename);      
    }
    function getDirCount($filepath){
        // first remove any upward directories
        $filepath = eregi_replace('\.\./', '', $filepath);
        if(!$filepath || $filepath==''){ return(0); }
        $directories = explode('/', $filepath);
        if(is_file($filepath)){
            array_pop($directories);        
        }
        if(eregi('/$', $filepath)){
            $dirCount = count($directories)-1; 
        }else{
            $dirCount = count($directories);
        }
        return($dirCount);            
    }
    function estimateBackupSize($directories){
        $backupSize = 0;
        // given an array of directories/files
        foreach($directories as $directory){
            $directory = $this->wordperssRootDir.trim($directory);
            if(is_file($directory)){
                $size = filesize($directory);    
            }
            if(is_dir($directory)){
                $size = $this->get_dir_size($directory); // size in bytes
            }   
            // for debugging
            //echo '<br />'.$directory.' is '.$size.'<br />';
            $backupSize += $size;           
        }
        return($backupSize); 
    }
    function getFilesizeIn($unit='mb', $filesizeInBytes){
        // return backup size dependent upon units
        switch($unit){
            case "gb":
                return(round(($filesizeInBytes / (1024*1024*1024)),2));
                break;
            case "mb":      
                return(round(($filesizeInBytes / (1024*1024)),2));
                break;
            case "kb":
                return(round(($filesizeInBytes / 1024),2)); 
                break;
        }         
    }
    function option($option){
        return(get_option($this->optionID($option)));    
    }
    function optionID($option){
        return($this->optionPrefix.'_'.$option);    
    }
    function optionExists($option){
        $option = $this->option($option);
        if(!$option || $option==''){ return(false); }
        return(true);
    }
    function getRandomAlphaString($length){
        /* http://stackoverflow.com/questions/4842868/generting-random-alphanumeric-string-in-php */
        for($i=0; $i<$length; $i++){
            $randomAlphaString .= chr(rand(0,25)+65);
        } 
        return($randomAlphaString);      
    }              
                
    
    /* Adapted methods */
    function get_dir_size($dir_name){
        /* 
        adapted from http://www.php.net/manual/en/function.disk-total-space.php#34100
        khumlo at gmail dot com  
        */        
        $dir_size =0;            //echo  is_dir($dir_name).' - '.$dir_name.'<br />'; 
           if (is_dir($dir_name)) {
               if ($dh = opendir($dir_name)) {
                  while (($file = readdir($dh)) !== false) {
                        if($file !="." && $file != ".."){  
                              if(is_file($dir_name."/".$file)){ 
                                  
                                   $dir_size += filesize($dir_name."/".$file);
                             }
                             /* check for any new directory inside this directory */
                             if(is_dir($dir_name."/".$file)){
                                $dir_size +=  $this->get_dir_size($dir_name."/".$file);
                              }
                              
                           }
                     }
                     closedir($dh);
             }
       }   
       return $dir_size;    
    }
    function removeTempBackupDirectory($dir, $backupTimeString) {
        /* 
        adapted from http://www.php.net/manual/en/function.rmdir.php 
        holger1 at NOSPAMzentralplan dot de 26-Jun-2010 09:00
        */
        // directory deletion safeguards
        // never ever ever delete the root!
        if(!$dir || $dir=='' || $dir=='../' || $dir=='/../'){ return; }
        // ensure this is a temp backup directory by checking the time string
        if(eregi($backupTimeString, $dir)===false){return;}

        if (is_dir($dir)) {
         $objects = scandir($dir);
         foreach ($objects as $object) {
           if ($object != "." && $object != "..") { 
             $file = $dir.$object;
               
             if (filetype($file) == "dir") {
                 //echo "remove directory: ".$file."<br />"; 
                 rrmdir($dir.$object); 
             }
             else {
                 //echo "remove file: ".$file."<br />";
                 unlink($dir.$object);
             }
           }
         }
         reset($objects);
         //echo "remove final directory: $dir"; 
         rmdir($dir);
        }
    } 
    function copy_directory( $source, $destination ) {
    if ( is_dir( $source ) ) {
        @mkdir( $destination );
        $directory = dir( $source );
        while ( FALSE !== ( $readdirectory = $directory->read() ) ) {
            if ( $readdirectory == '.' || $readdirectory == '..' ) {
                continue;
            }
            $PathDir = $source . '/' . $readdirectory; 
            if ( is_dir( $PathDir ) ) {
                copy_directory( $PathDir, $destination . '/' . $readdirectory );
                continue;
            }
            copy( $PathDir, $destination . '/' . $readdirectory );
        }
 
        $directory->close();
    }else {
        copy( $source, $destination );
    }
}

    function error($message){
        $this->errors++;
        ?>
        <div style="padding:10px;margin-top:25px;background:red;color:white;font-weight:bold;"><?PHP echo $message; ?></div>
        <?PHP 
    }
    function success($message){
        ?>
        <div style="padding:10px;margin-top:25px;background:green;color:white;font-weight:bold;"><?PHP echo $message; ?></div>
        <?PHP 
    }                                                      
}
// execute plugin
new WPInstantBackup();
?>