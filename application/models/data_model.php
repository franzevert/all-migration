<?php

class Data_model extends CI_Model
{
    function data_model()
    {
        // Call the Model constructor
        parent::__construct();
    }

    /**
     * @return mixed
     */
    function get_news()
    {
        //$this->db->select("id, title, version");
        //return $this->db->get('content');

        $sql = "SELECT d1.*
                FROM content d1
                LEFT OUTER JOIN content d2 ON (d1.navigation_id = d2.navigation_id and d1.version < d2.version)
                WHERE d2.navigation_id IS NULL
                ORDER BY `d1`.`navigation_id` DESC";

        return $this->db->query($sql);
    }

    //get the latest version of the content id we want
    function get_content_blocks($content_id, $tag)
    {
        $this->db->select("value");
        $this->db->where("content_id", $content_id);
        $this->db->where("tag", $tag);
        return $this->db->get('content_blocks');
    }

    //creating news items
    function create_wp_post($post_title, $post_content, $post_date, $category_id = "2")
    {
        $WPDB = $this->load->database('wordpress', TRUE);

        //build query to insert into wp_posts
        $data = array(
            'post_title'     => $post_title,
            'post_content'   => $post_content,
            'post_date'      => $post_date,
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'post_author'    => 1
        );

        $WPDB->insert('wp_posts', $data);
        log_message("debug", "Created wp_posts ID " . $WPDB->insert_id() . ": " . $post_title);

        //now associate with a category (2=news)
        $data = array(
            'object_id'        => $WPDB->insert_id(),
            'term_taxonomy_id' => $category_id
        );

        $WPDB->insert('wp_term_relationships', $data);
    }


    /**
     * @return mixed
     */
    function get_events()
    {
        //looks like a copy of the content table but just holding events data
        $sql = "SELECT d1.*
                FROM events d1
                LEFT OUTER JOIN events d2 ON (d1.root_id = d2.root_id and d1.version < d2.version)
                WHERE d1.published AND d2.root_id IS NULL
                ORDER BY d1.root_id DESC";

        return $this->db->query($sql);
    }


    //creating archival events
    function create_wp_event($row)
    {
        $WPDB = $this->load->database('wordpress', TRUE);

        //Events are stored in wp_posts and wp_post_meta

        $data = array(
            'post_author'    => 1,
            'post_date'      => $row->published,
            'post_content'   => $row->body,
            'post_title'     => $row->title,
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'post_type'      => 'event-list-cal'
        );

        //log_message("debug", print_r($data, 1));


        $WPDB->insert('wp_posts', $data);

        log_message("debug", "Created wp_posts EVENT ID " . $WPDB->insert_id());


        //TODO - check if there's already a postmeta record with a


        /**
         * add the meta data, e.g. rows like these
         *
         * post_id meta_key       meta_value
         * 619     event-date     2016-02-09
         * 619        event-days     1
         * 619        event-repeat   0
         * 619        event-end      0
         */

        $event_post_id = $WPDB->insert_id();

        //start date
        $data = array(
            'post_id'    => $event_post_id,
            'meta_key'   => 'event-date',
            'meta_value' => $row->start_date,
        );

        $WPDB->insert('wp_postmeta', $data);

        $event_days = 1;

        //event days
        if ($row->start_date !== $row->end_date) {
            //multiple day event?
            $start = new DateTime($row->start_date);
            $end = new DateTime($row->end_date);

            $event_days = $end->diff($start)->format("%a");

            log_message("debug", "original event dates " . $row->start_date . " - " . $row->end_date . " = " . $event_days);

            $end_date = $row->end_date;
        } else {
            $end_date = 0;
        }

        //event days
        $data = array(
            'post_id'    => $event_post_id,
            'meta_key'   => 'event-days',
            'meta_value' => $event_days,
        );

        $WPDB->insert('wp_postmeta', $data);

        //end_date
        $data = array(
            'post_id'    => $event_post_id,
            'meta_key'   => 'event-end',
            'meta_value' => $end_date,
        );

        $WPDB->insert('wp_postmeta', $data);

        //event repeat? nooo.
        $data = array(
            'post_id'    => $event_post_id,
            'meta_key'   => 'event-repeat',
            'meta_value' => 0,
        );

        $WPDB->insert('wp_postmeta', $data);

    }

}

?>