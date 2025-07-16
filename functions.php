<?php

add_action( 'wp_enqueue_scripts', 'trekky_child_enqueue_styles' );
function trekky_child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

add_filter('woocommerce_product_get_dimensions', 'hide_product_dimensions_on_product_page', 10, 2);
function hide_product_dimensions_on_product_page($dimensions, $product) {
    // Ensure dimensions are only hidden on the frontend
    if (!is_admin()) {
        return ''; // Return empty to hide dimensions
    }
    return $dimensions; // Keep dimensions in the admin dashboard
}

add_filter('woocommerce_product_get_data', 'remove_dimensions_from_additional_info', 10, 2);
function remove_dimensions_from_additional_info($data, $product) {
    if (!is_admin() && isset($data['dimensions'])) {
        $data['dimensions'] = ''; // Remove dimensions data
    }
    return $data;
}

add_filter('woocommerce_product_get_attributes', 'hide_empty_product_attributes', 10, 2);
function hide_empty_product_attributes($attributes, $product) {
    foreach ($attributes as $key => $attribute) {
        if (empty($attribute['options']) || (is_array($attribute['options']) && count($attribute['options']) === 0)) {
            unset($attributes[$key]); // Remove attribute if it has no values
        }
    }
    return $attributes;
}

function display_store_navigation_links($position) {
    // Static navigation links conditionally displayed either before or after the categories
    if ($position == 'start') {
        echo '<li class="menu-item"><a href="' . esc_url(home_url()) . '">Pagrindinis</a></li>';
    }

    if ($position == 'end') {
        echo '<li class="menu-item"><a href="' . esc_url(home_url('/apie-mus')) . '">Apie</a></li>';
        echo '<li class="menu-item"><a href="' . esc_url(home_url('/kontaktai')) . '">Kontaktai</a></li>';
    }
}

// Custom sorting function to sort categories alphabetically by name
function custom_sort_categories_by_name($a, $b) {
    return strcmp($a->name, $b->name); // Alphabetical sorting by name
}

function display_product_categories_hierarchy($parent_id = 0, $first = true) {
    $args = array(
        'taxonomy'     => 'product_cat',
        'hide_empty'   => false,
        'parent'       => $parent_id,
        'orderby'      => 'name',        // Sort categories alphabetically by name
        'order'        => 'ASC',         // Ascending alphabetical order
    );

    // Get categories
    $categories = get_categories($args);

    // Apply custom sorting logic to categorize
    if ($categories) {
        usort($categories, 'custom_sort_categories_by_name');

        if ($first) {
            echo '<div class="mega-menu-wrapper">'; // Main wrapper for the full mega menu
            echo '<ul class="boutiquemenu mega-menu">'; // Top-level menu container

            // Display Home link first
            display_store_navigation_links('start');
        }

        // Loop through top-level category items
        foreach ($categories as $category) {
            // Prepare child categories for this category
            $child_args = array(
                'taxonomy'     => 'product_cat',
                'hide_empty'   => false,
                'parent'       => $category->term_id,
                'orderby'      => 'name',        // Sort child categories alphabetically
                'order'        => 'ASC',         // Ascending alphabetical order
            );
            $child_categories = get_categories($child_args);

            // Skip top-level categories without subcategories (only at first level)
            if ($first && empty($child_categories)) {
                continue;
            }

            // Main category link
            $category_link = get_term_link($category);
            if (is_wp_error($category_link)) {
                error_log('Error generating link for category: ' . $category->name . ' (ID: ' . $category->term_id . ')');
                continue;
            }

            // Output the main category item
            echo '<li class="menu-item">';
            echo '<a href="' . esc_url($category_link) . '">' . esc_html($category->name) . '</a>';

            if ($first) {
                echo '<span class="chevron"><i class="fa fa-chevron-down"></i></span>'; // Chevron for top-level categories
            }

            echo '</li>';

            // Display subcategories if they exist
            if (!empty($child_categories)) {
                echo '<div class="mega-menu-content" data-category="' . $category->term_id . '">'; // Mega menu content container
                echo '<div class="mega-menu-columns">'; // Mega menu columns container

                // Display child categories in columns
                display_columns_of_subcategories($category->term_id);

                echo '</div>'; // Close mega-menu-columns
                echo '</div>'; // Close mega-menu-content
            }
        }

        // If it's the first level, display About Us and Contact Us at the end
        if ($first) {
            display_store_navigation_links('end');
            echo '</ul>'; // Close top-level menu
            echo '</div>'; // Close mega-menu-wrapper
        }
    }
}

function display_columns_of_subcategories($parent_id) {
    $args = array(
        'taxonomy'     => 'product_cat',
        'hide_empty'   => false,
        'parent'       => $parent_id,
        'orderby'      => 'name',
        'order'        => 'ASC'
    );

    $categories = get_categories($args);

    // Array to hold subcategories without sub-subcategories
    $no_sub_subcategories = [];
    $columns = []; // Array to hold all columns

    if ($categories) {
        foreach ($categories as $category) {
            $child_args = array(
                'taxonomy'     => 'product_cat',
                'hide_empty'   => false,
                'parent'       => $category->term_id
            );
            $child_categories = get_categories($child_args);

            $category_link = get_term_link($category);
            if (is_wp_error($category_link)) {
                continue;
            }

            // If the category has sub-subcategories, add it to the columns array
            if (!empty($child_categories)) {
                ob_start(); // Start output buffering
                echo '<div class="mega-menu-column">';
                echo '<a href="' . esc_url($category_link) . '"" class="mega-menu-item">' . esc_html($category->name) . '</a>';

                // Recursively display subcategories
                display_product_categories_hierarchy($category->term_id, false);

                echo '</div>';
                $columns[] = ob_get_clean(); // Save the column output
            } else {
                // Collect categories without further children
                $no_sub_subcategories[] = $category;
            }
        }

        // If there are subcategories without sub-subcategories, add them to the columns array
        if (!empty($no_sub_subcategories)) {
            ob_start();
            echo '<div class="mega-menu-column">';
            foreach ($no_sub_subcategories as $subcategory) {
                $subcategory_link = get_term_link($subcategory);
                if (is_wp_error($subcategory_link)) {
                    continue;
                }
                echo '<a href="' . esc_url($subcategory_link) . '" class="mega-menu-item">' . esc_html($subcategory->name) . '</a>';
            }
            echo '</div>';
            $columns[] = ob_get_clean();
        }

        // Reorder columns: Move the last column to the front
        if (!empty($columns)) {
            $last_column = array_pop($columns); // Get the last column
            array_unshift($columns, $last_column); // Add it to the front
        }

        // Output the columns
        foreach ($columns as $column) {
            echo $column;
        }
    }
}

// Shortcode function to display product categories hierarchy
function product_categories_hierarchy_shortcode() {
    ob_start();

    // Display the categories as normal
    display_product_categories_hierarchy();

    // Add the category-content div where AJAX will load the content
    echo '<div id="category-content">';
    echo '<!-- AJAX-loaded category content will appear here -->';
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('product_categories_hierarchy', 'product_categories_hierarchy_shortcode');

function get_filtered_attributes_for_category() {
    // Ensure we are on a product category page
    if (!is_product_category()) {
        return '<p>This filter is only available on category pages.</p>';
    }

    // Get the current category object
    $current_category = get_queried_object();
    $category_slug = $current_category->slug;

    // Retrieve all WooCommerce attributes
    $attributes = wc_get_attribute_taxonomies();
    if (empty($attributes)) {
        return '<p>No attributes found.</p>';
    }

    // Get products in the current category
    $product_ids = wc_get_products(array(
        'category' => array($category_slug),
        'return' => 'ids',
        'limit' => -1,
    ));

    if (empty($product_ids)) {
        return '<p>No products found in this category.</p>';
    }

    // Prepare dropdowns for each attribute
    $output = '<div class="woocommerce-attribute-dropdowns">';

    foreach ($attributes as $attribute) {
        $taxonomy = 'pa_' . $attribute->attribute_name;

        // Get terms that are used by products in this category
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'object_ids' => $product_ids,
        ));

        if (empty($terms) || is_wp_error($terms)) {
            continue;
        }

        // Create a dropdown for the attribute
        $output .= '<div class="attribute-dropdown" style="margin-bottom: 15px;">';
        $output .= '<label for="' . esc_attr($taxonomy) . '">' . esc_html($attribute->attribute_label) . '</label>';
        $output .= '<select id="' . esc_attr($taxonomy) . '" class="ajax-filter-select">';
        $output .= '<option value="">' . __('Select ' . $attribute->attribute_label, 'woocommerce') . '</option>';

        // Create correct WooCommerce filter links for each term
        foreach ($terms as $term) {
            $filter_url = add_query_arg(array(
                'filter_' . $taxonomy => $term->slug,
            ), get_term_link($current_category));

            if (!is_wp_error($filter_url)) {
                $output .= '<option value="' . esc_url($filter_url) . '">' . esc_html($term->name) . '</option>';
            }
        }

        $output .= '</select></div>';
    }

    $output .= '</div>';

    return $output;
}
add_shortcode('category_filtered_attributes', 'get_filtered_attributes_for_category');

//add_action('woocommerce_product_query', 'apply_all_attribute_filters');
function apply_all_attribute_filters($q) {
    if (is_product_category() && !is_admin()) {
        $tax_query = $q->get('tax_query');

        // Loop through all GET parameters to find attribute filters
        foreach ($_GET as $key => $value) {
            // Check if the parameter starts with 'filter_pa_'
            if (strpos($key, 'filter_pa_') === 0 && !empty($value)) {
                // Extract attribute taxonomy from the URL parameter
                $taxonomy = str_replace('filter_', '', $key);

                // Add the taxonomy filter to the query
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($value),
                    'operator' => 'IN',
                );
            }
        }

        // Set the updated tax_query to the WooCommerce query
        $q->set('tax_query', $tax_query);
    }
}

add_action('wp_ajax_filter_products', 'filter_products_callback');
add_action('wp_ajax_nopriv_filter_products', 'filter_products_callback');
function filter_products_callback() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
    );

    // Check if we are resetting the filters
    if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
        // No filtering, return all products
    } else if (isset($_GET['filters'])) {
        $tax_query = array('relation' => 'AND');

        foreach ($_GET['filters'] as $taxonomy => $value) {
            $tax_query[] = array(
                'taxonomy' => sanitize_text_field($taxonomy),
                'field'    => 'slug',
                'terms'    => sanitize_text_field($value),
            );
        }

        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }
    } else {
        echo '<p>No products found matching your selection.</p>';
    }

    wp_reset_postdata();
    wp_die();
}

// Function to display static links in the mobile menu
function display_mobile_navigation_links($position) {
    // Static navigation links conditionally displayed either before or after the categories in the mobile menu
    if ($position == 'start') {
        echo '<li class="mobile-menu-item menu-static-link"><a href="' . esc_url(home_url()) . '">Pagrindinis</a></li>';
    }

    if ($position == 'end') {
        echo '<li class="mobile-menu-item menu-static-link"><a href="' . esc_url(home_url('/apie-mus')) . '">Apie</a></li>';
        echo '<li class="mobile-menu-item menu-static-link"><a href="' . esc_url(home_url('/kontaktai')) . '">Kontaktai</a></li>';
    }
}

function display_mobile_product_categories_hierarchy($parent_id = 0, $first = true) {
    $args = array(
        'taxonomy'     => 'product_cat',
        'hide_empty'   => false,         // Do not hide empty categories
        'parent'       => $parent_id,    // Start with the parent category
        'orderby'      => 'name',        // Sort categories alphabetically
        'order'        => 'ASC',         // Ascending order
    );

    // Fetch categories based on arguments
    $categories = get_categories($args);

    if ($categories) {
        // Sort categories so those with children come first
        usort($categories, function ($a, $b) {
            $child_a = get_categories(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => $a->term_id
            ));
            $child_b = get_categories(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => $b->term_id
            ));

            // Categories with children come first
            return (!empty($child_b) - !empty($child_a));
        });

        if ($first) {
            echo '<nav class="mobile-menu-tab mobile-navigation mobile-pages-menu active" aria-label="Mobilioji navigacija">';
            echo '<div class="handheld-navigation">';
            echo '<ul id="menu-menu" class="menu mobile-product-categories-menu">';  // Main mobile menu container

            // Add static links before product categories
            display_mobile_navigation_links('start');
        }

        // Loop through sorted categories
        foreach ($categories as $category) {
            // Check if the category has children
            $child_args = array(
                'taxonomy'     => 'product_cat',
                'hide_empty'   => false,
                'parent'       => $category->term_id,
                'orderby'      => 'name',
                'order'        => 'ASC',
            );
            $child_categories = get_categories($child_args);

            // Skip categories without children if it's the first level
            if ($first && empty($child_categories)) {
                continue;
            }

            // Prepare category link
            $category_link = get_term_link($category);
            if (is_wp_error($category_link)) {
                continue;  // Skip if there's an error with the category link
            }

            // Output the category item
            echo '<li class="mobile-menu-item">';
            echo '<a href="' . esc_url($category_link) . '">';

            // Display category name
            echo esc_html($category->name);

            // Only display chevron if subcategories exist
            if (!empty($child_categories)) {
                echo '<span class="chevron"><i class="fa fa-chevron-down"></i></span>';
            }

            echo '</a>';

            // Display subcategories if they exist
            if (!empty($child_categories)) {
                echo '<ul class="mobile-subcategories">';
                display_mobile_product_categories_hierarchy($category->term_id, false);  // Recursively display subcategories
                echo '</ul>';
            }

            echo '</li>';
        }

        if ($first) {
            // Add static links after product categories
            display_mobile_navigation_links('end');
            echo '</ul>';  // Close main mobile menu container
            echo '</div>';  // Close handheld-navigation
            echo '</nav>';  // Close nav mobile menu
        }
    }
}

// Shortcode function to display the mobile product categories
function mobile_product_categories_hierarchy_shortcode() {
    ob_start();

    // Call the mobile-specific function to display categories
    display_mobile_product_categories_hierarchy();

    return ob_get_clean();
}

// Add shortcode for mobile product categories
add_shortcode('mobile_product_categories', 'mobile_product_categories_hierarchy_shortcode');

function custom_replace_newsletter_text_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            function replaceNewsletterText() {
                const newsletterLabels = document.querySelectorAll('label[for="subscribe-to-newsletter"]');
                newsletterLabels.forEach(label => {
                    label.childNodes.forEach(node => {
                        if (node.nodeType === Node.TEXT_NODE && node.textContent.includes("Email me with news and offers")) {
                            node.textContent = "El. paštu informuokite apie naujienas ir pasiūlymus";
                        }
                    });
                });
            }

            // Pakeitimai puslapio įkėlimo metu
            replaceNewsletterText();

            // DOM stebėjimas realiuoju laiku
            const observer = new MutationObserver(() => {
                replaceNewsletterText();
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_replace_newsletter_text_script');

function custom_replace_new_in_store_text_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            const newInStoreTitle = document.querySelector('h2.wp-block-heading.has-text-align-center');
            if (newInStoreTitle && newInStoreTitle.textContent.includes("New in store")) {
                newInStoreTitle.textContent = "Nauja parduotuvėje"; // Replace with the Lithuanian translation
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_replace_new_in_store_text_script');

function custom_replace_error_message_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // Observe DOM changes (useful for dynamically loaded error messages)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === "childList") {
                        const errorMessage = document.querySelector('.woocommerce-error'); // WooCommerce error class
                        if (errorMessage && errorMessage.textContent.includes("The username")) {
                            errorMessage.textContent = errorMessage.textContent
                                .replace("Error: The username", "Klaida: Vartotojo vardas")
                                .replace("is not registered on this site.", "nėra registruotas šiame puslapyje.")
                                .replace("If you are unsure of your username, try your email address instead.", "Jei nesate tikri dėl savo vartotojo vardo, pabandykite el. pašto adresą.");
                        }
                    }
                });
            });

            // Start observing the body for changes
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_replace_error_message_script');

function translate_privacy_policy_text_script() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // Find the specific <p> tag containing the text
            const privacyPolicyText = document.querySelector('p:has(.woocommerce-privacy-policy-link)');
            if (privacyPolicyText) {
                // Replace the full text including the link
                privacyPolicyText.innerHTML = "Jūsų asmens duomenys bus naudojami siekiant palaikyti jūsų patirtį šiame tinklalapyje, valdyti prieigą prie jūsų paskyros ir kitais tikslais, aprašytais mūsų <a href='https://darkblue-bear-449880.hostingersite.com/privatumo-politika/' class='woocommerce-privacy-policy-link' target='_blank'>privatumo politikoje</a>.";
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'translate_privacy_policy_text_script');

function custom_woocommerce_ordering_text($translated_text, $text, $domain) {
    switch ($translated_text) {
        case 'Default sorting':
            $translated_text = 'Numatytasis rūšiavimas';
            break;
        case 'Sort by popularity':
            $translated_text = 'Rūšiuoti pagal populiarumą';
            break;
        case 'Sort by average rating':
            $translated_text = 'Rūšiuoti pagal vidutinį įvertinimą';
            break;
        case 'Sort by latest':
            $translated_text = 'Rūšiuoti pagal naujausią';
            break;
        case 'Sort by price: low to high':
            $translated_text = 'Pigiausi viršuje';
            break;
        case 'Sort by price: high to low':
            $translated_text = 'Brangiausi viršuje';
            break;
    }
    return $translated_text;
}
add_filter('gettext', 'custom_woocommerce_ordering_text', 20, 3);

add_action('woocommerce_product_query', 'move_out_of_stock_products_to_end');
function move_out_of_stock_products_to_end($query) {
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
        $meta_query = $query->get('meta_query');

        // Add a stock status condition to the query
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '='
            ),
            array(
                'key' => '_stock_status',
                'value' => 'outofstock',
                'compare' => '='
            ),
        );

        $query->set('meta_query', $meta_query);

        // Add sorting by stock status
        $query->set('orderby', array(
            'meta_value' => 'ASC', // Ensures "instock" comes before "outofstock"
            'title' => 'ASC'      // Secondary sort by product title
        ));
    }
}

function custom_form_validation_script() {
    // Tikriname, ar tai tinkamas puslapis (jei reikia)
    if (is_page('/')) { // Pakeiskite 'your-page-slug' į tikslinio puslapio URL dalį arba naudokite is_front_page() jei tai pagrindinis puslapis.
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const form = document.querySelector(".elementor-form-fields-wrapper"); // Formos selektorius

                if (form) {
                    form.addEventListener("submit", function (event) {
                        const emailField = form.querySelector("input[type='email']");
                        let valid = true;

                        // Patikrinkite, ar el. pašto laukas yra užpildytas
                        if (!emailField.value) {
                            valid = false;
                            emailField.setCustomValidity("Prašome įvesti savo el. pašto adresą."); // Čia pakeiskite tekstą
                            emailField.reportValidity();
                        } else {
                            emailField.setCustomValidity(""); // Išvalykite klaidos pranešimą, jei laukas užpildytas
                        }

                        if (!valid) {
                            event.preventDefault(); // Sustabdykite formos pateikimą
                        }
                    });
                }
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'custom_form_validation_script');

add_filter( 'woocommerce_product_get_width', 'hide_single_product_dimensions', 25, 2 );
add_filter( 'woocommerce_product_get_height', 'hide_single_product_dimensions', 25, 2 );
add_filter( 'woocommerce_product_get_length', 'hide_single_product_dimensions', 25, 2 );
function hide_single_product_dimensions($value, $product ){
    // Only on single product pages
    if( is_product() )
        $value = '';

    return $value;
}

add_filter( 'woocommerce_product_get_weight', 'hide_single_product_weight', 25, 2 );
function hide_single_product_weight( $value, $product ){
    // Only on single product pages
    if( is_product() )
        $value = '';

    return $value;
}

add_filter( 'woocommerce_breadcrumb_defaults', 'change_breadcrumb_text_to_lithuanian' );
function change_breadcrumb_text_to_lithuanian( $defaults ) {
    $defaults['home'] = 'Pagrindinis'; // Pakeičia "Home" tekstą
    return $defaults;
}

add_filter( 'gettext', 'translate_checkout_text', 20, 3 );
function translate_checkout_text( $translated_text, $text, $domain ) {
    if ( $text === 'Email me with news and offers' ) {
        $translated_text = 'Atsiųskite man naujienas ir pasiūlymus';
    }
    return $translated_text;
}

add_action( 'wp_footer', 'change_checkout_text_with_js' );
function change_checkout_text_with_js() {
    if ( is_checkout() ) :
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const label = document.querySelector('label[for="subscribe-to-newsletter"]');
                if (label) {
                    label.innerHTML = label.innerHTML.replace('Email me with news and offers', 'Atsiųskite man naujienas ir pasiūlymus');
                }
            });
        </script>
    <?php
    endif;
}

/**
 * Enable searching products by EAN in the admin "All Products" search.
 */
add_filter( 'woocommerce_product_data_store_cpt_get_products_query', 'enable_admin_search_by_ean', 10, 2 );
function enable_admin_search_by_ean( $query, $query_vars ) {
    // Only modify the query in the admin if there's a search term.
    if ( is_admin() && ! empty( $query_vars['s'] ) ) {
        $ean_value = wc_clean( $query_vars['s'] );

        // Add a meta_query to search by your EAN meta field.
        $query['meta_query'][] = array(
            'key'     => '_ean', // <-- Replace with your actual EAN meta key
            'value'   => $ean_value,
            'compare' => 'LIKE',
        );
    }

    return $query;
}

function custom_lost_password_validation_messages() {
    // Check if we're on the WooCommerce "lost-password" endpoint
    if ( function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('lost-password') ) :
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Try to select the lost password form
                // Adjust these selectors if needed
                var lostPasswordForm = document.querySelector(
                    'form.woocommerce-ResetPassword,' +
                    'form.woocommerce-FormLostPassword,' +
                    'form.woocommerce-form-login,' +
                    'form#lostpasswordform,' +
                    'form.lost_reset_password'
                );

                if (lostPasswordForm) {
                    // Find any required fields in the form
                    var requiredFields = lostPasswordForm.querySelectorAll('input[required], textarea[required]');

                    requiredFields.forEach(function(field) {
                        // When the field is invalid (empty or type mismatch), set custom Lithuanian message
                        field.addEventListener('invalid', function(e) {
                            e.target.setCustomValidity(''); // Clear any previous message

                            if (!e.target.validity.valid) {
                                if (e.target.validity.valueMissing) {
                                    // Empty field
                                    e.target.setCustomValidity('Prašome užpildyti šį lauką.');
                                } else if (e.target.validity.typeMismatch && e.target.type === 'email') {
                                    // Email format mismatch
                                    e.target.setCustomValidity('Prašome įvesti teisingą el. pašto adresą.');
                                }
                            }
                        });

                        // Clear custom message when user starts typing
                        field.addEventListener('input', function(e) {
                            e.target.setCustomValidity('');
                        });
                    });
                }
            });
        </script>
    <?php
    endif;
}
add_action('wp_footer', 'custom_lost_password_validation_messages');

function my_custom_delivers_to_translation( $translated_text, $original_text, $domain ) {
    if ( 'Delivers to ' === $original_text ) {
        $translated_text = 'Pristatome į'; // Or "Pristatoma į" if you prefer a passive form
    }
    return $translated_text;
}
add_filter( 'gettext', 'my_custom_delivers_to_translation', 20, 3 );

function translate_login_error_message( $translated_text, $text, $domain ) {
    if ( 'Wrong username or password. Please try again!!!' === $text ) {
        $translated_text = 'Neteisingas vartotojo vardas arba slaptažodis. Bandykite dar kartą.';
    }
    return $translated_text;
}
add_filter( 'gettext', 'translate_login_error_message', 20, 3 );

function custom_woocommerce_register_email_validation() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Find the registration form using its classes
            var $registerForm = $('form.woocommerce-form.woocommerce-form-register.register');
            if (!$registerForm.length) return;

            // Target the email field within the form
            var $emailField = $registerForm.find('input[type="email"]');
            if (!$emailField.length) return;

            // When the email field is invalid, override the message with our custom one
            $emailField.on('invalid', function(e) {
                // Prevent default message from appearing
                e.preventDefault();
                // Clear any previous custom validity messages
                this.setCustomValidity('');

                // Check for an empty value or a type mismatch and set our custom message
                if (this.validity.valueMissing) {
                    this.setCustomValidity('Prašome užpildyti šį lauką.');
                } else if (this.validity.typeMismatch) {
                    this.setCustomValidity('Prašome įvesti teisingą el. pašto adresą.');
                }
            });

            // Reset the custom message when the user starts typing
            $emailField.on('input', function() {
                this.setCustomValidity('');
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'custom_woocommerce_register_email_validation');

add_action('wp_footer', function() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const translations = {
                'Subtotal': 'Tarpinė suma',
                'Delivery': 'Pristatymas',
                'Total': 'Viso'
            };

            const translate = () => {
                document.querySelectorAll('.wc-block-components-totals-item__label').forEach(el => {
                    const text = el.textContent.trim();
                    if (translations[text]) {
                        el.textContent = translations[text];
                    }
                });
            };

            translate();

            const observer = new MutationObserver(translate);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const translations = [
                {
                    selector: '.wc-block-checkout__guest-checkout-notice',
                    original: 'You are currently checking out as a guest.',
                    translation: 'Šiuo metu perkate kaip svečias.'
                },
                {
                    selector: '.wc-block-components-checkbox__label',
                    original: 'Create an account with Žalumynas.lt',
                    translation: 'Sukurti paskyrą Žalumynas.lt parduotuvėje'
                },
                {
                    selector: '.wc-block-components-checkbox__label',
                    original: 'By proceeding with your purchase you agree to our Taisyklių ir sąlygų puslapis and ',
                    contains: true, // we want to check only part of the string
                    translation: 'Tęsdami pirkimą sutinkate su mūsų Taisyklėmis ir privatumo politika'
                },
                {
                    selector: '.wc-block-components-totals-coupon__input-coupon',
                    original: 'Enter code',
                    contains: true, // we want to check only part of the string
                    translation: 'Įveskite kodą'
                }
            ];

            const replaceText = () => {
                translations.forEach(({selector, original, translation, contains = false}) => {
                    document.querySelectorAll(selector).forEach(el => {
                        const text = el.textContent.trim();
                        if ((contains && text.includes(original)) || (!contains && text === original)) {
                            el.textContent = translation;
                        }
                    });
                });
            };

            replaceText();

            const observer = new MutationObserver(replaceText);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const replaceAddress2Toggle = () => {
                document.querySelectorAll('.wc-block-components-address-form__address_2-toggle').forEach(el => {
                    const text = el.textContent.trim();
                    if (text === '+ Add butas, blokas ir pan.') {
                        el.textContent = '+ Pridėti buto nr.';
                    }
                });
            };

            // Run immediately
            replaceAddress2Toggle();

            // Watch for dynamic updates
            const observer = new MutationObserver(replaceAddress2Toggle);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const translations = [
                {
                    selector: '.content-content',
                    original: 'Sign In / Register',
                    translation: 'Prisijungti / Registruotis'
                },
                {
                    selector: '.mobile-tab-title.mobile-pages-title.active span',
                    original: 'Main menu',
                    translation: 'Meniu'
                }
            ];

            const replaceText = () => {
                translations.forEach(({ selector, original, translation }) => {
                    document.querySelectorAll(selector).forEach(el => {
                        if (el.textContent.trim() === original) {
                            el.textContent = translation;
                        }
                    });
                });
            };

            replaceText();

            const observer = new MutationObserver(replaceText);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>


    <?php
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'custom-barlow-font',
        'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap&subset=latin,latin-ext',
        [],
        null
    );
});

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const translations = [
                {
                    selector: '#textinput-8',
                    original: 'Create a password',
                    translation: 'Sukurkite slaptažodį'
                },
                {
                    selector: '.components-base-control__label, .css-2o4jwd, .ej5x27r2',
                    original: 'Please select the country',
                    translation: 'Pasirinkite šalį'
                }
            ];

            const replaceText = () => {
                translations.forEach(({ selector, original, translation }) => {
                    document.querySelectorAll(selector).forEach(el => {
                        const text = el.textContent.trim();
                        if (text === original) {
                            el.textContent = translation;
                        }
                    });
                });
            };

            replaceText();

            const observer = new MutationObserver(replaceText);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        (function () {
            const targetSelector = 'label[for="wc-block-components-totals-coupon__input-coupon"]';
            const translateLabel = () => {
                const label = document.querySelector(targetSelector);
                if (label && label.textContent.trim() === 'Enter code') {
                    label.textContent = 'Įveskite kodą'; // <- Your translation
                }
            };

            const observer = new MutationObserver(() => {
                translateLabel();
            });

            document.addEventListener('DOMContentLoaded', () => {
                const config = { childList: true, subtree: true };
                observer.observe(document.body, config);
                translateLabel(); // Run once immediately
            });
        })();
    </script>
    <?php
});

add_action('wp_footer', function () {
    if (is_404()) {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const backButton = document.querySelector('.button-error .go-back');
                if (backButton) {
                    backButton.setAttribute('href', 'https://www.zalumynas.lt/');
                    backButton.setAttribute('target', '_self'); // optional: open in same tab
                }
            });
        </script>
        <?php
    }
});

add_action('wp_head', function () {
    ?>
    <style>
        #woocommerce-password-strength-meter-0-result {
            display: none !important;
        }
    </style>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const translations = [
                {
                    selector: 'label[for="textinput-8"]',
                    original: 'Create a password',
                    translation: 'Sukurkite slaptažodį'
                },
                {
                    selector: '#validate-error-account-password span',
                    original: 'Please enter a valid password',
                    translation: 'Įveskite galiojantį slaptažodį'
                },
                {
                    selector: '#validate-error-account-password span',
                    original: 'Please create a stronger password',
                    translation: 'Sukurkite stipresnį slaptažodį'
                }
            ];

            const translateText = () => {
                translations.forEach(({ selector, original, translation }) => {
                    document.querySelectorAll(selector).forEach(el => {
                        if (el.textContent.trim() === original) {
                            el.textContent = translation;
                        }
                    });
                });
            };

            translateText();

            const observer = new MutationObserver(translateText);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const targetSelector = '.wc-block-components-checkbox__label';
            const originalText = 'Naudoti tą patį adresą';
            const translatedText = 'Naudoti tą patį adresą pristatymui ir atsiskaitymui';

            const translate = () => {
                document.querySelectorAll(targetSelector).forEach(el => {
                    if (el.textContent.trim() === originalText) {
                        el.textContent = translatedText;
                    }
                });
            };

            translate();

            const observer = new MutationObserver(translate);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_filter( 'woocommerce_get_script_data', 'custom_password_strength_labels', 10, 2 );
function custom_password_strength_labels( $params, $handle ) {
    if ( 'wc-password-strength-meter' === $handle ) {
        $params['i18n_password_error'] = 'Prašome įvesti stipresnį slaptažodį.';
        $params['i18n_password_hint']  = 'Naudokite bent 8 simbolius, įskaitant didžiąsias ir mažąsias raides, skaičius ir specialius simbolius.';
        $params['i18n_password_strength'] = array(
            'short'    => 'Per silpnas',
            'bad'      => 'Silpnas',
            'good'     => 'Vidutinis',
            'strong'   => 'Stiprus',
            'mismatch' => 'Slaptažodžiai nesutampa',
        );
    }
    return $params;
}

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const translateStickyCartLabel = () => {
                document.querySelectorAll('.trekky-sticky-add-to-cart__content-title').forEach(el => {
                    // Find the first text node (before the <strong>)
                    const nodes = Array.from(el.childNodes);
                    const textNode = nodes.find(node => node.nodeType === Node.TEXT_NODE && node.textContent.includes("You're viewing"));

                    if (textNode && textNode.textContent.trim() === "You're viewing:") {
                        textNode.textContent = "Jūs žiūrite: ";
                    }
                });
            };

            const translateNoResults = () => {
                document.querySelectorAll('.product-title').forEach(el => {
                    if (el.textContent.trim() === 'No results') {
                        el.textContent = 'Produktas nerastas';
                    }
                });
            };

            // Run both on load
            translateStickyCartLabel();
            translateNoResults();

            // Observe DOM for dynamic content
            const observer = new MutationObserver(() => {
                translateStickyCartLabel();
                translateNoResults();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('init', 'rename_product_brand_taxonomy', 11);
function rename_product_brand_taxonomy() {
    // Unregister original taxonomy
    unregister_taxonomy('product_brand');

    // Register it again with Lithuanian slug and label
    register_taxonomy('product_brand', 'product', array(
        'label' => 'Prekės ženklas',
        'labels' => array(
            'name' => 'Prekės ženklai',
            'singular_name' => 'Prekės ženklas',
            'menu_name' => 'Prekės ženklai',
            'all_items' => 'Visi ženklai',
            'edit_item' => 'Redaguoti ženklą',
            'view_item' => 'Žiūrėti ženklą',
            'update_item' => 'Atnaujinti ženklą',
            'add_new_item' => 'Pridėti naują ženklą',
            'new_item_name' => 'Naujo ženklo pavadinimas',
            'search_items' => 'Ieškoti ženklų',
        ),
        'public' => true,
        'hierarchical' => false,
        'rewrite' => array('slug' => 'prekes-zenklas', 'with_front' => true),
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
    ));
}

add_action('wp_footer', function () {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const translateTaxNote = () => {
                document.querySelectorAll('.wc-block-components-totals-footer-item-tax').forEach(el => {
                    const span = el.querySelector('span');
                    if (span && el.textContent.includes('Including') && el.textContent.includes('in taxes')) {
                        const taxAmount = span.outerHTML;
                        el.innerHTML = `Įskaičiuota ${taxAmount} mokesčių`;
                    }
                });
            };

            translateTaxNote();

            const observer = new MutationObserver(translateTaxNote);
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
    <?php
});

add_action('init', function () {
    if (current_user_can('administrator') && isset($_GET['delete_gyvunu_preke'])) {

        $target_slug = 'gyvunu-prekes';

        // Query all products in the specified category
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $target_slug,
                'include_children' => false
            ]]
        ];

        $query = new WP_Query($args);
        $deleted = 0;

        foreach ($query->posts as $product) {
            wp_delete_post($product->ID, true); // true = force delete (no trash)
            $deleted++;
        }

        echo "✅ Deleted {$deleted} products in category '{$target_slug}'.";
        exit;
    }
});

add_filter('woocommerce_order_shipping_taxable', '__return_true');

function skaicius_zodziais( $skaicius )
{
    // neskaiciuosim neigiamu ir itin dideliu skaiciu (iki milijardu)
    if ( $skaicius < 0 || strlen( $skaicius ) > 9 ) return '';

    if ( $skaicius == 0 ) return 'nulis';

    $vienetai = array( '', 'vienas', 'du', 'trys', 'keturi', 'penki', 'šeši', 'septyni', 'aštuoni', 'devyni' );

    $niolikai = array( '', 'vienuolika', 'dvylika', 'trylika', 'keturiolika', 'penkiolika', 'šešiolika', 'septyniolika', 'aštuoniolika', 'devyniolika' );

    $desimtys = array( '', 'dešimt', 'dvidešimt', 'trisdešimt', 'keturiasdešimt', 'penkiasdešimt', 'šešiasdešimt', 'septyniasdešimt', 'aštuoniasdešimt', 'devyniasdešimt' );

    $pavadinimas = array(
        array( 'milijonas', 'milijonai', 'milijonų' ),
        array( 'tūkstantis', 'tūkstančiai', 'tūkstančių' ),
    );

    $skaicius = sprintf( '%09d', $skaicius ); // iki milijardu 10^9 (milijardu neskaiciuosim)
    $skaicius = str_split( $skaicius, 3 ); // kertam kas tris simbolius

    $zodziais = array();

    foreach ( $skaicius as $i => $tripletas ) {

        // resetinam linksni
        $linksnis = 0;

        // pridedam simtu pavadinima, jei pirmas tripleto skaitmuo > 0
        if ( $tripletas[0] > 0 ) {
            $zodziais[] = $vienetai[ $tripletas[0]];
            $zodziais[] = ( $tripletas[0] > 1 ) ? 'šimtai' : 'šimtas';
        }

        // du paskutiniai tripleto skaiciai
        $du = substr( $tripletas, 1 );

        // pacekinam nioliktus skaicius
        if ( $du > 10 && $du < 20 ) {
            $zodziais[] = $niolikai[ $du[1]];
            $linksnis = 2;
        } else {

            // pacekinam desimtis
            if ( $du[0] > 0 ) {
                $zodziais[] = $desimtys[ $du[0]];
            }

            // pridedam vienetus
            if ( $du[1] > 0 ) {
                $zodziais[] = $vienetai[ $du[1]];
                $linksnis = ( $du[1] > 1 ) ? 1 : 0;
            } else {
                $linksnis = 2;
            }

        }

        // pridedam pavadinima isskyrus paskutiniam ir nuliniams tripletams
        if ( $i < count( $pavadinimas ) && $tripletas != '000' ) {
            $zodziais[] = $pavadinimas[ $i ][ $linksnis ];
        }

    }

    return implode( ' ', $zodziais );
}

function valiutos_galune( $number, $saknis = 'eur' )
{
    if ( $number < 0 || strlen( $number ) > 9 ) return '';

    if ( $number == 0 ) {
        return $saknis . 'ų';
    }

    $last = substr( $number, -1 );
    $du = substr( $number, -2, 2 );

    if ( ($du > 10) && ($du < 20) ) {
        return $saknis . 'ų';
    } else {
        if ( $last == 0 ) {
            return $saknis . 'ų';
        } elseif ( $last == 1 ) {
            return $saknis . 'as';
        } else {
            return $saknis . 'ai';
        }
    }
}
