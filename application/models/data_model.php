<?php

class Data_model extends CI_Model
{
    function data_model()
    {
        // Call the Model constructor
        parent::__construct();
    }

    function get_news()
    {
        //Using the navigation table to drive this
        $sql = <<<EOSQL
select id, title, root_id, url, published, created
from navigation
where parent_id = 39
and visible = 1
and expired IS NULL
and deleted IS NULL
and url != ""
and root_id = id
and published ORDER BY `id` ASC
EOSQL;

        return $this->db->query($sql);
    }

    //creating news items
    function create_wp_post($post_title, $post_content, $post_date, $post_name, $category_id = "8")
    {
        $WPDB = $this->load->database('wordpress', TRUE);

        //build query to insert into wp_posts
        $data = array(
            'post_type'      => 'post',
            'post_title'     => $post_title,
            'post_content'   => $post_content,
            'post_date'      => $post_date,
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'post_author'    => 4,
            'post_name'      => $post_name
        );

        $WPDB->insert('cwvl_posts', $data);

        //log_message("debug", "Created wp_posts ID " . $WPDB->insert_id() . ": " . $post_title);
        echo "<li>Created post id - " . $WPDB->insert_id() . "<li>";

        //now associate with a category (8=news)
        $data = array(
            'object_id'        => $WPDB->insert_id(),
            'term_taxonomy_id' => $category_id
        );

        $WPDB->insert('term_relationships', $data);

        //echo $this->db->last_query(); exit;
    }

    //creating news items

    //get the latest version of the content id we want
    /*    function get_content_blocks($content_id, $tag)
        {
            $this->db->select("value");
            $this->db->where("content_id", $content_id);
            $this->db->where("tag", $tag);
            return $this->db->get('content_blocks');
        }


        //get the latest version of the content id we want - USING THE TITLE to get the highest ID (version)
        function get_latest_content_id_by_title($title)
        {
            $this->db->where("value", $title);
            $this->db->where("tag", "heading");
            $this->db->order_by("id", "desc");
            return $this->db->get('content_blocks');
        }*/

    //fetching content for an id in navigation table
    function get_content_for_id($id, $tag = "heading")
    {

        $sql = <<<EOSQL
        select * from content c, content_blocks cb
        where c.id = cb.content_id
        and cb.tag = '$tag'
        and c.navigation_id = $id
EOSQL;

        return $this->db->query($sql);
    }

    function get_events()
    {
        //looks like a copy of the content table but just holding events data
        $sql = "SELECT d1.*
FROM events d1
LEFT OUTER JOIN events d2 ON (d1.root_id = d2.root_id and d1.version < d2.version)
WHERE d1.published
AND d2.root_id IS NULL
AND d1.deleted IS NULL
ORDER BY d1.root_id DESC";

        return $this->db->query($sql);
    }

    //creating archival events
    function create_wp_event($row)
    {
        $WPDB = $this->load->database('wordpress', TRUE);

        //$post_name = $this->get_unique_post_slug($row->title); // this will be done manually after import with Migrate/slug_unique()

        //Events are stored in wp_posts and wp_post_meta
        $data = array(
            'post_author'    => 1,
            'post_date'      => $row->published,
            'post_content'   => $row->body,
            'post_title'     => $row->title,
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'post_type'      => 'event-list-cal',
            'post_name'      => url_title($row->title, "-", TRUE),
            'post_author'    => 37

        );

        //log_message("debug", print_r($data, 1));

        $WPDB->insert('posts', $data);


        log_message("debug", "Created wp_posts EVENT ID " . $WPDB->insert_id());

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

        $WPDB->insert('postmeta', $data);

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

        $WPDB->insert('postmeta', $data);

        //end_date
        $data = array(
            'post_id'    => $event_post_id,
            'meta_key'   => 'event-end',
            'meta_value' => $end_date,
        );

        $WPDB->insert('postmeta', $data);

        //event repeat? nooo.
        $data = array(
            'post_id'    => $event_post_id,
            'meta_key'   => 'event-repeat',
            'meta_value' => 0,
        );

        $WPDB->insert('postmeta', $data);

    }


/*    function get_all_wp_events(){
        $WPDB = $this->load->database('wordpress', TRUE);
        $WPDB->where("post_type", "event-list-cal");
        $WPDB->order_by("post_name");
        return $WPDB->get("posts");
    }*/

    function get_unique_wp_posts(){
        $WPDB = $this->load->database('wordpress', TRUE);

        $sql =<<<SQL
SELECT id, post_name FROM `cwvl_posts`
WHERE post_type = 'event-list-cal'
GROUP BY post_name
SQL;
        return $WPDB->query($sql);
    }


    function fetch_duplicates($post_name, $ID){
        $WPDB = $this->load->database('wordpress', TRUE);
        $WPDB->where("post_name", $post_name);
        $WPDB->where("post_type", "event-list-cal");
        $WPDB->where("ID !=", $ID);
        return $WPDB->get("posts");
    }


    function update_post_name($new_post_name, $id){
        $WPDB = $this->load->database('wordpress', TRUE);
        $WPDB->set('post_name', $new_post_name);
        $WPDB->where('id', $id);
        $WPDB->update('posts');
    }

    /**
     * multiple events can have the same title, but they need unique slugs...
     */
 /*   function get_unique_post_slug($title)
    {
        $WPDB = $this->load->database('wordpress', TRUE);

        //see if this slug exists already
        $post_name = url_title($title, "-", TRUE);

        $WPDB->where("post_name", $post_name);
        $result = $WPDB->get("posts");

        $numfound = $result->num_rows();

        if ($numfound == 0) {
            return $post_name;
        } else {

            //at this point we've either got my-post-name or my-post-name-1
            //if it's my-post-name-SOMETHING, increment

            $suffix = substr($post_name, -2);
            echo "SUFFIX IS... " . $suffix;
            echo "<br>";


            if ($suffix == "-1") {

                return substr($post_name, 0, -1) . "2";

            } elseif ($suffix == "-2") {

                return substr($post_name, 0, -1) . "3";

            } elseif ($suffix == "-3") {

                return substr($post_name, 0, -1) . "4";

            } elseif ($suffix == "-4") {

                return substr($post_name, 0, -1) . "5";

            } elseif ($suffix == "-5") {

                return substr($post_name, 0, -1) . "6";

            } elseif ($suffix == "-6") {

                return substr($post_name, 0, -1) . "7";

            } elseif ($suffix == "-7") {

                return substr($post_name, 0, -1) . "8";

            } elseif ($suffix == "-8") {

                return substr($post_name, 0, -1) . "9";

            } else {

                return $this->get_unique_post_slug($post_name . "-" . $numfound);
            }
        }
    }*/

}

?>