<?php

class DAA_Amazon_Widget extends WP_Widget {

    public $options;

    //Constructor
    public function __construct() {
        parent::__construct( 'saa_widget', __( 'Amazon Affiliate Widget', 'saa' ), array('description' => __( 'DuoGeek Amazon Affiliate Widget', 'saa' ),) );
        $this->options = get_option( 'saa_options' );
    }

    /*
     * 
     * Widget Initialization
     * 
     */

    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'Amazon Products', 'saa' );
        echo $title;
        echo do_shortcode( '[dg_saa]' );
        echo $args['after_widget'];
    }

    /*
     * 
     * Widget Front End
     * 
     */

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : __( 'Amazon Products', 'saa' );
        ?>
        <p>
            <label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:', 'df' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <?php
    }

    /*
     * 
     * Widget Update
     * 
     */

    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = (!empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

        return $instance;
    }

}

function saa_register_widgets() {
    register_widget( 'DAA_Amazon_Widget' );
}

add_action( 'widgets_init', 'saa_register_widgets' );

