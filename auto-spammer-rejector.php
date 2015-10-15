<?php
/**
 * @package Automatic_Spammer_Rejector
 * @version 0.2
 */
/*
Plugin Name: Automatic Spammer Rejector
Plugin URI: http://www.hadacchi.com/
Description: This plugin count spammers comment according to marks by Akismet. This plugin read the data from the database of your wordpress to count the number of comment marked `spam'. 
Author: hadacchi
Version: 0.2
Author URI: http://www.hadacchi.com/
*/

class AutoSpammerRejector {
    // a table which this plugin makes and uses
    const POSTFIX = 'spammer_ips';
    // option name where this plugin stores a last modified date 
    const SIPS_LAST_DATE='sips_last_updated';
    // parameters which are initialized in constructor
    var $table_name;
    var $threshold;

    // constructor
    public function __construct()
    {
        global $wpdb;
        // set table name
        $this->table_name = $wpdb->prefix . self::POSTFIX;
        $this->threshold = 10;
        // perform when this plugin is activated
        register_activation_hook (__FILE__, array($this, 'sips_activate'));
        // update spammers' ips when any comments are posted
        add_action( 'comment_post', array($this, 'sips'));
        // if a spammer try to post comments, reject
        add_action( 'comment_post', array($this, 'ip_check') );
        // reject spammers completely
        //add_action( 'init', array($this, 'ip_check') );
        // output ip addr and the number of spam comments of each spammer (site admin)
        //add_action( 'admin_notices', array($this, 'sips_out') );
        // output ip addr and the number of spam comments of each spammer (dashboard)
        add_action( 'wp_dashboard_setup', array($this, 'sips_dashboard_widget') );
    }

    // dashboard setup
    function sips_dashboard_widget() {
        wp_add_dashboard_widget( 'com.hadacchi.sips', 'Spammer IPs', array($this, 'sips_out') );
    }

    // block spammers' access
    function ip_check() {
        if ( !empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $blacklist = $this->get_blocked_ip();
            foreach ($blacklist as $value){
                if ($ip == $value->ip_addr) wp_die('You are denied...');
            }
        }
    }

    // called when this plugin is activated
    function sips_activate() {
        global $wpdb;
        $sql = "CREATE TABLE " . $this->table_name . "(
            ip_addr varchar(16) NOT NULL,
            counter int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (ip_addr)
        );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // driver of spammer ip extractor
    function sips(){
        $ips = $this->sips_read();
        $this->sips_update($ips);
    }

    // called from sips()
    function sips_read(){
        global $wpdb;
        $lastdate=$this->get_last_date();
        $sql="SELECT comment_author_IP AS ip_addr,COUNT(comment_approved) AS counter FROM {$wpdb->comments} WHERE comment_approved='spam'"
            .(is_null($lastdate)?"":" AND comment_date_gmt>'".$lastdate->format('Y-m-d H:i:s')."'")
            ." GROUP BY ip_addr";
        $result = $wpdb->get_results($sql);
        update_option(self::SIPS_LAST_DATE,date('Y-m-d H:i:s'));
        return $result;
    }

    // called from sips()
    function sips_update($diff_records){
        global $wpdb;
        foreach ($diff_records as $value) {
            $result=$wpdb->get_row("SELECT * from $this->table_name WHERE ip_addr='$value->ip_addr'");
            if (is_null($result)) { 
                $wpdb->insert($this->table_name,array('ip_addr'=>$value->ip_addr,'counter'=>$value->counter),array('%s','%s'));
            } else {
                $wpdb->update($this->table_name,array('counter'=>(int)$result->counter+$value->counter),array('ip_addr'=>$result->ip_addr));
            }
        }
    }

    // called from ip_check()
    function get_blocked_ip() {
        global $wpdb;
        //return $wpdb->get_results("SELECT * FROM $this->table_name");
        return $wpdb->get_results("SELECT * FROM $this->table_name WHERE counter>$this->threshold");
    }

    // called from sips_read()
    function get_last_date() {
        $alloptions=wp_load_alloptions();
        return is_null($alloptions[self::SIPS_LAST_DATE])?NULL:new DateTime($alloptions[self::SIPS_LAST_DATE]);
    }

    // output ip and the number of spam comments of each spammer
    function sips_out() {
        $lastdate=$this->get_last_date();
        $lastdate->setTimezone(new DateTimeZone('Asia/Tokyo'));
        echo "<p>last scaned at " . (is_null($lastdate)?"NULL":$lastdate->format('Y-m-d H:i:s')) . "</p>";
        echo "<p id='spam-ip-ex'>spammers are</p><table><tr><th>ip addr</th><th>counter</th></tr>";
        $result = $this->get_blocked_ip();
        foreach ($result as $value) {
            echo '<tr><td>' . $value->ip_addr ."</td><td style='text-align:right;'>".$value->counter."</td></tr>";
        }
        echo "</table>";
    }
}
$sips = new AutoSpammerRejector;

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
        echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
        exit;
}



?>
