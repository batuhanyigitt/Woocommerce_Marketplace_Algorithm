<?php
/*
Plugin Name: Tuvval Algorithmic Sorting
Plugin URI: https://tuvval.com
Description: Bu eklenti, woocommerce ürünlerinin kategori sıralamasını düzenler.
Version: 1.0
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if ( ! class_exists( 'Tuvval_Algorithmic_Sorting' ) ) :

class Tuvval_Algorithmic_Sorting {

    public function __construct() {
        add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'custom_woocommerce_get_catalog_ordering_args' ) );
        add_filter( 'woocommerce_default_catalog_orderby_options', array( $this, 'custom_woocommerce_catalog_orderby' ) );
        add_filter( 'woocommerce_catalog_orderby', array( $this, 'custom_woocommerce_catalog_orderby' ) );
    }

    public function custom_woocommerce_catalog_orderby( $sortby ) {
        $sortby['bestforyou'] = __( 'Sizin İçin Önerilen', 'woocommerce' );
        return $sortby;
    }

    public function custom_woocommerce_get_catalog_ordering_args( $args ) {
        if ( isset( $_GET['orderby'] ) ) {
            if ( 'bestforyou' == $_GET['orderby'] ) {
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'desc';
                $args['meta_key'] = 'post_score';
            }
        }
        return $args;
    }

}

endif;

function Tuvval_Algorithmic_Sorting() {
    return new Tuvval_Algorithmic_Sorting();
}

Tuvval_Algorithmic_Sorting();

function tasv_default_product_orderby() {
    return 'bestforyou';
}
add_filter('woocommerce_default_catalog_orderby', 'tasv_default_product_orderby');

function tasv_custom_product_sorting_query( $args ) {
    if ( isset( $args['orderby'] ) && 'bestforyou' == $args['orderby'] ) {
        $args = array_merge( $args, array(
            'meta_key' => 'post_score',
            'orderby'  => 'meta_value_num',
            'order'    => 'desc',
        ) );
    }
    return $args;
}
add_filter( 'woocommerce_get_catalog_ordering_args', 'tasv_custom_product_sorting_query' );

function tasv_custom_product_sorting( $options ) {
    $new_options = [];
    $new_options['bestforyou'] = __( 'Sizin İçin Önerilen', 'woocommerce' );
    return array_merge($new_options, $options);
}
add_filter( 'woocommerce_catalog_orderby', 'tasv_custom_product_sorting', 10 );
add_filter( 'woocommerce_default_catalog_orderby_options', 'tasv_custom_product_sorting', 10 );

function calculate_post_score($post_id) {
    global $wpdb;
    $score = 0;

    // Points
    $pro_user_score_point = get_option('pro_user_score_point', '12');
    $amateur_user_score_point = get_option('amateur_user_score_point', '-100');
    $wishlist_score_point = get_option('wishlist_score_point', '1');
    $sales_score_point = get_option('sales_score_point', '10');
    $comments_score_point = get_option('comments_score_point', '3');
    $featured_score_point = get_option('featured_score_point', '5');

    // post sahibini al
    $post_author_id = get_post_field( 'post_author', $post_id );

    // post sahibi profesyonel ise +5 puan
    if(get_user_meta($post_author_id, 'user_type', true) == 'profesyonel') {
        $score += $pro_user_score_point;
        $details['user_score'] = $pro_user_score_point;
    }

    // post sahibi amatör ise -100 puan
    if(get_user_meta($post_author_id, 'user_type', true) == 'amator') {
        $score -= $amateur_user_score_point;
        $details['user_score'] = $amateur_user_score_point;
    }

    // ürün wishliste eklendi ise +1 puan (her ekleme için +1)
    $sql = "SELECT COUNT(`ID`) as count FROM `wp_yith_wcwl` WHERE `prod_id` = '".$post_id."';";
    $likes = $wpdb->get_var($sql);
    if($likes) {
        $wishlist_score = $likes * $wishlist_score_point;
        $score += $wishlist_score;
        $details['wishlist_score'] = $wishlist_score;
    }

    // ürün daha önce satıldı ise +5 puan (her satış için +5)
    // $sales = get_post_meta($post_id, 'total_sales', true);
    $sql = "SELECT COUNT(`meta_id`) as count FROM `wp_woocommerce_order_itemmeta` WHERE `meta_key` = '_product_id' AND `meta_value` = '".$post_id."';";
    $sales = $wpdb->get_var($sql);
    if($sales) {
        $sales_score = $sales * $sales_score_point;
        $score += $sales_score;
        $details['sales_score'] = $sales_score;
    }

    // üründe yorum varsa +1 puan (her yorum için +1)
    $comments_count = wp_count_comments( $post_id );
    if($comments_count->approved) {
        $comments_score = $comments_count->approved * $comments_score_point;
        $score += $comments_score;
        $details['comments_score'] = $comments_score;
    }

    // featured seçildi ise +5 puan
    $featured_score = 0;
    if(has_term( 'featured', 'product_visibility', $post_id )) {
        $score += $featured_score_point;
        $details['featured_score'] = $featured_score_point;
    }

    $array = array('score' => $score, 'details' => $details);
    return $array;
}

add_action('save_post', 'update_post_score', 10, 3);

function update_post_score($post_id, $post, $update) {
    // Eğer yeni bir post eklenmişse veya mevcut bir post güncellenmişse score'u hesaplayıp meta olarak ekliyoruz
    if ($post->post_type == 'product' && $post->post_status == 'publish') {
        $score = calculate_post_score($post_id);
        update_post_meta($post_id, 'post_score', $score['score']);
        update_post_meta($post_id, 'post_score_details', $score['details']);
    }
}

add_action('wp_insert_comment', 'update_post_score_on_comment', 10, 2);

function update_post_score_on_comment($id, $comment) {
    $post_id = $comment->comment_post_ID;
    $score = calculate_post_score($post_id);
    update_post_meta($post_id, 'post_score', $score['score']);
    update_post_meta($post_id, 'post_score_details', $score['details']);
}

add_action('updated_user_meta', 'update_all_user_posts_score_on_profession_change', 10, 4);

function update_all_user_posts_score_on_profession_change($meta_id, $user_id, $meta_key, $_meta_value) {
    // Eğer kullanıcının profesyonel ya da amatör statüsü değiştiyse, bu kullanıcının tüm postlarının score'unu güncelliyoruz
    if ($meta_key == 'profession') {
        $args = array(
            'author'        =>  $user_id,
            'post_type'     =>  'product',
            'posts_per_page' => -1
        );
        $user_posts = get_posts( $args );
        foreach ($user_posts as $post) {
            $score = calculate_post_score($post->ID);
            update_post_meta($post->ID, 'post_score', $score);
        }
    }
}

function tasv_algorithmic_sorting_admin_menu() {
    // Ana sayfa
    add_menu_page(
        'Algorithmic Sorting',
        'Tuvval Sorting',
        'manage_options',
        'tuvval-algorithmic-sorting',
        'tuvval_algorithmic_sorting_admin_page',
        'dashicons-tickets',
        6
    );

    // Algoritmic Posts alt menüsü
    add_submenu_page(
        'tuvval-algorithmic-sorting',
        'Algoritmic Posts',
        'Algoritmic Posts',
        'manage_options',
        'tuvval-algorithmic-posts',
        'tuvval_algorithmic_posts_subpage'
    );

    // Users alt menüsü
    add_submenu_page(
        'tuvval-algorithmic-sorting',
        'Algoritmic Users',
        'Algoritmic Users',
        'manage_options',
        'tuvval-users',
        'tuvval_users_subpage'
    );
}

add_action('admin_menu', 'tasv_algorithmic_sorting_admin_menu');

function tuvval_register_settings() {
    register_setting('tuvval-settings-group', 'pro_user_score_point');
    register_setting('tuvval-settings-group', 'amateur_user_score_point');
    register_setting('tuvval-settings-group', 'wishlist_score_point');
    register_setting('tuvval-settings-group', 'sales_score_point');
    register_setting('tuvval-settings-group', 'comments_score_point');
    register_setting('tuvval-settings-group', 'featured_score_point');
}

add_action('admin_init', 'tuvval_register_settings');

function tuvval_algorithmic_sorting_admin_page() {
?>
    <div class="wrap">
        <h1>Tuvval Algorithmic Sorting</h1>
        <p>Tuvval algorithmic sorting eklentisi aşağıdaki kurallara göre puanlama yapmaktadır. Kullanıcı seçimini kullanıcı listesinden yapabilirsiniz.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'tuvval-settings-group' ); ?>
            <?php do_settings_sections( 'tuvval-settings-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Pro User Score Point</th>
                    <td><input type="text" name="pro_user_score_point" value="<?php echo esc_attr(get_option('pro_user_score_point')); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Amateur User Score Point</th>
                    <td><input type="text" name="amateur_user_score_point" value="<?php echo esc_attr(get_option('amateur_user_score_point')); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Wishlist Score Point</th>
                    <td><input type="text" name="wishlist_score_point" value="<?php echo esc_attr(get_option('wishlist_score_point')); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Sales Score Point</th>
                    <td><input type="text" name="sales_score_point" value="<?php echo esc_attr(get_option('sales_score_point')); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Comments Score Point</th>
                    <td><input type="text" name="comments_score_point" value="<?php echo esc_attr(get_option('comments_score_point')); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Featured Score Point</th>
                    <td><input type="text" name="featured_score_point" value="<?php echo esc_attr(get_option('featured_score_point')); ?>" /></td>
                </tr>
            </table>

            <?php submit_button(); ?>

        </form>
    </div>
<?php
}

function tuvval_algorithmic_posts_subpage(){
  ?>
  <div class="wrap">
      <h1>Tuvval Algorithmic Sorting | Product Points</h1>
      <div style="overflow: auto;">
        <?php
        $total_posts = wp_count_posts('product')->publish;
        echo '<input type="hidden" id="total-posts" value="' . $total_posts . '">';
         ?>
        <button id="score-all-posts" class="button button-primary" style="float:left;margin-right:5px;">Tüm postları değerlendir</button>
        <img id="loading" src="<?php echo plugin_dir_url(__FILE__); ?>/img/loading.gif" style="display: none; height:24px;">
        <div id="score-result"></div>
        <span style="margin-top:3px;">Bu buton ile puanlamayı tekrar gerçekleştirebilirsiniz. Ürün sayısı arttıkça işlem süresi uzayacaktır.</span>
      </div>
      <hr>
      <div style="overflow: auto;">
        <p>
          Aşağıdaki tabloda en yüksek puan alan 100 ürün görüntülenmektedir. Puanlama kontrolü için kullanabilirsiniz.
        </p>
      </div>
      <hr>
      <table class="widefat fixed" cellspacing="0">
        <thead>
          <tr>
            <th id="title" class="manage-column column-title" scope="col"><b>Ürün Adı</b></th>
            <th id="category" class="manage-column column-category" scope="col"><b>Kategori</b></th>
            <th id="score" class="manage-column column-score" scope="col"><b>Score</b></th>
            <th id="details" class="manage-column column-details" scope="col"><b>Details</b></th>
          </tr>
        </thead>
        <?php
          $args = array(
              'post_type'     =>  'product',
              'posts_per_page' => 100,
              'meta_key' => 'post_score',
              'orderby'  => 'meta_value_num',
          );
          $posts = get_posts( $args );
          foreach ($posts as $post) {
            $product_id = $post->ID;
            $title = get_the_title($post);
            $score = get_post_meta($product_id, 'post_score', true);
            $details = get_post_meta($product_id, 'post_score_details', true);
            $terms = get_the_terms( $product_id, 'product_cat' );
            foreach ($terms as $term) {
                $product_category = $term->name;
                break;
            }
            ?>
            <tr>
              <th><?php echo $title; ?></th>
              <td><?php echo $product_category; ?></td>
              <td><?php echo $score; ?></td>
              <td><?php print_r($details); ?></td>
            </tr>
            <?php
          }
        ?>
      </table>
  </div>
  <?php
}

function tuvval_users_subpage() {
    // Profesyonel kullanıcıları al
    $pro_users = get_users(array(
        'meta_key' => 'user_type',
        'meta_value' => 'profesyonel'
    ));

    // Amatör kullanıcıları al
    $amateur_users = get_users(array(
        'meta_key' => 'user_type',
        'meta_value' => 'amator'
    ));

    ?>
    <div class="wrap">
        <h1>Tuvval Algorithmic Sorting | Users</h1>
        <h2>Profesyonel Kullanıcılar</h2>
        <ul>
            <?php
            foreach($pro_users as $user) {
                echo '<li>' . $user->display_name . '</li>';
            }
            ?>
        </ul>

        <h2>Amatör Kullanıcılar</h2>
        <ul>
            <?php
            foreach($amateur_users as $user) {
                echo '<li>' . $user->display_name . '</li>';
            }
            ?>
        </ul>
    </div>
    <?php
}

function tasv_algorithmic_sorting_admin_scripts() {
  wp_enqueue_script('tasv-admin-script', plugin_dir_url(__FILE__) . 'js/admin-script.js', array('jquery'), '1.6', true);
  wp_localize_script('tasv-admin-script', 'tasv_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('admin_enqueue_scripts', 'tasv_algorithmic_sorting_admin_scripts');

function score_posts() {
    $offset = intval($_POST['offset']);
    $number = intval($_POST['number']);

    $args = array(
        'post_type' => 'product',
        'offset' => $offset,
        'posts_per_page' => $number
    );

    $posts = get_posts($args);

    foreach ($posts as $post) {
        $post_id = $post->ID;
        $score = calculate_post_score($post_id);
        if(empty($score['score'])){
          $score['score'] = 0;
        }
        update_post_meta($post_id, 'post_score', $score['score']);
        if(!empty($score['details'])){
          update_post_meta($post_id, 'post_score_details', $score['details']);
        }
    }

    wp_die();
}
add_action('wp_ajax_score_posts', 'score_posts');


// function tasv_score_posts_ajax() {
//     // Check nonce for security
//     if ( ! check_ajax_referer( 'tasv_score_posts', 'security', false ) ) {
//         wp_send_json_error( 'Invalid nonce' );
//     }
//
//     $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
//
//     $args = array(
//         'post_type' => 'product',
//         'posts_per_page' => 1,
//         'offset' => $offset,
//     );
//
//     $query = new WP_Query( $args );
//
//     while ( $query->have_posts() ) {
//         $query->the_post();
//         tasv_calculate_post_score( get_the_ID() );
//     }
//
//     wp_reset_postdata();
//
//     if ( $query->max_num_pages > $offset + 1 ) {
//         wp_send_json_success( $offset + 1 );
//     } else {
//         wp_send_json_success( 'done' );
//     }
// }
//
// add_action( 'wp_ajax_tasv_score_posts', 'tasv_score_posts_ajax' );


////////////////////////////////////
//  Profesyonel Kulanıcı Seçimi  //
//////////////////////////////////

// Kullanıcı türünü ana kullanıcılar tablosuna ekle

function tuvval_add_user_type_column($columns) {
    $columns['user_type'] = 'Üye Tipi';
    return $columns;
}

add_filter('manage_users_columns', 'tuvval_add_user_type_column');

function tuvval_show_user_type_column_content($value, $column_name, $user_id) {
    if ($column_name == 'user_type') {
        $user_type = get_user_meta($user_id, 'user_type', true);
        $checked_profesyonel = $user_type === 'profesyonel' ? 'checked' : '';
        $checked_amator = $user_type === 'amator' ? 'checked' : '';
        $checked_default = ($user_type !== 'profesyonel' && $user_type !== 'amator') ? 'checked' : '';
        return sprintf('<input type="radio" id="default_%d" name="user_type_%d" class="tuvval-user-type" data-user_id="%d" data-user_type="" %s /> <label for="default_%d">Varsayılan</label> <input type="radio" id="pro_%d" name="user_type_%d" class="tuvval-user-type" data-user_id="%d" data-user_type="profesyonel" %s /> <label for="pro_%d">Profesyonel</label> <input type="radio" id="amator_%d" name="user_type_%d" class="tuvval-user-type" data-user_id="%d" data-user_type="amator" %s /> <label for="amator_%d">Amatör</label>', $user_id, $user_id, $user_id, $checked_default, $user_id, $user_id, $user_id, $user_id, $checked_profesyonel, $user_id, $user_id, $user_id, $user_id, $checked_amator, $user_id);
    }
    return $value;
}

add_filter('manage_users_custom_column', 'tuvval_show_user_type_column_content', 10, 3);

function tuvval_update_user_type() {

    // Doğrulama ve yetki kontrolü
    check_ajax_referer('tuvval_user_type', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Yetkisiz erişim.');
    }

    // Kullanıcı ID'sini ve türünü alın
    $user_id = intval($_POST['user_id']);
    $user_type = sanitize_text_field($_POST['user_type']);

    // Kullanıcı türünü güncelleyin
    if ($user_type === 'profesyonel' || $user_type === 'amator' || $user_type === '') {
        if($user_type === ''){
            delete_user_meta($user_id, 'user_type');
        }else{
            update_user_meta($user_id, 'user_type', $user_type);
        }
        wp_send_json_success();
    } else {
        wp_send_json_error('Geçersiz kullanıcı tipi.');
    }
}

add_action('wp_ajax_tuvval_update_user_type', 'tuvval_update_user_type');



function tuvval_add_user_type_nonce() {

    if (current_user_can('manage_options')) {

        wp_nonce_field('tuvval_user_type', 'tuvval_user_type_nonce');

    }

}

add_action('admin_footer', 'tuvval_add_user_type_nonce');


?>
