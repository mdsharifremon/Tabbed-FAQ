<?php
/**
 * Plugin Name:     Tabbed FAQ
 * Description:     FAQ CPT + Categories + front-end search/tabs/accordion.
 * Version:         1.0.0
 * Author:          Munna Chowdhury
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Custom_FAQ_Plugin {

    public function __construct(){
        add_action( 'init',                 [ $this, 'register_cpt_and_tax' ] );
        add_action( 'wp_enqueue_scripts',   [ $this, 'enqueue_css' ] );
        add_shortcode( 'custom_faqs',       [ $this, 'render_faqs' ] );
    }

    public function register_cpt_and_tax(){
        register_post_type( 'faq', [
            'labels' => [
                'name'          => __( 'FAQs', 'custom-faq' ),
                'singular_name' => __( 'FAQ', 'custom-faq' ),
            ],
            'public'        => true,
            'has_archive'   => false,
            'show_in_rest'  => true,
            'supports'      => [ 'title', 'editor' ],
            'rewrite'       => [ 'slug' => 'faq' ],
        ] );

        register_taxonomy( 'faq-category', 'faq', [
            'labels'            => [
                'name'          => __( 'Categories', 'custom-faq' ),
                'singular_name' => __( 'Category', 'custom-faq' ),
            ],
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'hide_empty'        => true,
            'rewrite'           => [ 'slug' => 'faq-category' ],
        ] );
    }

    public function enqueue_css(){
        wp_enqueue_style(
            'custom-faq-style',
            plugin_dir_url(__FILE__) . 'assets/faq-style.css',
            [],
            '1.2.2'
        );
    }

    public function render_faqs( $atts ){
        if ( doing_action( 'elementor/editor/init' ) ) {
            return '<div style="border:1px dashed #ccc; padding:10px; background:#f9f9f9;">
                        <strong>FAQ Preview:</strong> This is a placeholder in Elementor editor.
                    </div>';
        }

        wp_enqueue_script(
            'custom-faq-js',
            plugin_dir_url(__FILE__) . 'assets/faq-search.js',
            [ 'jquery' ],
            '1.2.2',
            true
        );

        $all = [];
        $posts = get_posts([
            'post_type'   => 'faq',
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'ASC',
        ]);
        foreach( $posts as $p ){
            $terms = wp_get_post_terms( $p->ID, 'faq-category' );
            $slug  = $terms ? $terms[0]->slug : '';
            $all[] = [
                'id'   => $p->ID,
                'cat'  => $slug,
                'q'    => get_the_title( $p ),
                'a'    => apply_filters( 'the_content', $p->post_content ),
            ];
        }

        wp_localize_script( 'custom-faq-js', 'faqData', $all );

        $terms = get_terms([
            'taxonomy'   => 'faq-category',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if ( is_wp_error($terms) || empty($terms) ) {
            return '<p>Nothing Found</p>';
        }

        ob_start(); ?>

        <div class="faq-search">
			<div class="search-icon search-icon-faq">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"><path fill="#fff" d="M10.383 16.767a6.4 6.4 0 0 0 3.71-1.196l3.935 3.935a.94.94 0 0 0 .673.274c.54 0 .93-.415.93-.947a.91.91 0 0 0-.266-.664l-3.91-3.918a6.36 6.36 0 0 0 1.312-3.868c0-3.51-2.872-6.383-6.384-6.383C6.863 4 4 6.872 4 10.383s2.864 6.384 6.383 6.384m0-1.378c-2.747 0-5.005-2.266-5.005-5.006 0-2.739 2.258-5.005 5.005-5.005 2.74 0 5.006 2.266 5.006 5.005 0 2.74-2.266 5.006-5.006 5.006" opacity=".8"/></svg>
			</div>
            <input type="text" class="faq-search-input" placeholder="Search for answers">
            <span class="faq-search-clear">×</span>
            <ul class="faq-suggestions"></ul>
        </div>

        <div class="faq-container">
            <div class="faq-tabs">
                <?php foreach( $terms as $i => $t ): ?>
                    <button class="faq-tab-btn<?php echo $i === 0 ? ' active' : '';?>" data-tab="<?php echo esc_attr($t->slug);?>">
                        <?php echo esc_html($t->name);?>
                    </button>
                <?php endforeach;?>
            </div>
            <div class="faq-content">
                <?php foreach( $terms as $i => $t ): ?>
                    <div class="faq-tab-content<?php echo $i === 0 ? ' active' : '';?>" id="<?php echo esc_attr($t->slug);?>">
                        <?php
                        global $post;
                        $original_post = $post;

                        $q = new WP_Query([
                            'post_type'      => 'faq',
                            'tax_query'      => [[
                                'taxonomy'=>'faq-category',
                                'field'   =>'slug',
                                'terms'   =>$t->slug
                            ]],
                            'orderby'        => 'date',
                            'order'          => 'ASC',
                            'posts_per_page' => -1,
                        ]);
                        if( $q->have_posts() ):
                            $opened_first = false;
                            while( $q->have_posts() ): $q->the_post(); ?>
                                <div class="faq-item">
                                    <div class="faq-question<?php if (!$opened_first) { echo ' open'; $opened_first = true; } ?>"><?php the_title();?></div>
                                    <div class="faq-answer"<?php if ($opened_first) echo ' style="display:block;"'; ?>><?php the_content();?></div>
                                </div>
                            <?php endwhile;
                        else:
                            echo '<p>Nothing Found</p>';
                        endif;

                        wp_reset_postdata();
                        $post = $original_post;
                        setup_postdata( $post );
                        ?>
                    </div>
                <?php endforeach;?>
            </div>
        </div>

        <div class="faq-mobile-nav">
            <?php foreach( $terms as $t ): ?>
                <button data-target="<?php echo esc_attr($t->slug);?>">
                    <?php echo esc_html($t->name);?>
                </button>
            <?php endforeach;?>
        </div>
        <div class="faq-mobile-content">
            <?php foreach( $terms as $t ): ?>
                <div class="faq-mobile-section" id="<?php echo esc_attr($t->slug);?>">
                    <h2><?php echo esc_html($t->name);?></h2>
                    <?php
                    global $post;
                    $original_post = $post;

                    $q = new WP_Query([
                        'post_type'      => 'faq',
                        'tax_query'      => [[
                            'taxonomy'=>'faq-category',
                            'field'   =>'slug',
                            'terms'   =>$t->slug
                        ]],
                        'orderby'        => 'date',
                        'order'          => 'ASC',
                        'posts_per_page' => -1,
                    ]);
                    if( $q->have_posts() ):
                        $opened_first = false;
                        while( $q->have_posts() ): $q->the_post(); ?>
                            <div class="faq-item">
                                <div class="faq-question<?php if (!$opened_first) { echo ' open'; $opened_first = true; } ?>"><?php the_title();?></div>
                                <div class="faq-answer"<?php if ($opened_first) echo ' style="display:block;"'; ?>><?php the_content();?></div>
                            </div>
                        <?php endwhile;
                    else:
                        echo '<p>Nothing Found</p>';
                    endif;

                    wp_reset_postdata();
                    $post = $original_post;
                    setup_postdata( $post );
                    ?>
                </div>
            <?php endforeach;?>
        </div>

        <?php
        return ob_get_clean();
    }
}

new Custom_FAQ_Plugin();
