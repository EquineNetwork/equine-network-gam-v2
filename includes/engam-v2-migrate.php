<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * One-time migration: copy legacy ACF sponsor IDs into the plugin's native
 * `_engam_v2_sponsor_id` meta so assignments survive deletion of the ACF
 * fields. Covers posts/pages (post meta) AND categories/tags/other taxonomies
 * (term meta) — both are read by the front-end. Reads meta directly (not
 * get_field) so it still works after the ACF field definitions are removed.
 * Never overwrites an existing plugin value, and is safe to run repeatedly.
 *
 * @param bool $write When false, performs a dry run (counts only, no changes).
 * @return array{candidates:int,migrated:int,skipped_existing:int,posts:int,terms:int,samples:array}
 */
if ( ! function_exists( 'engam_v2_migrate_acf_sponsors' ) ) {
    function engam_v2_migrate_acf_sponsors( $write = false ) {
        global $wpdb;
        if ( $write ) { @set_time_limit( 0 ); }  // large sites: avoid timing out mid-write

        $result = array(
            'candidates' => 0, 'migrated' => 0, 'skipped_existing' => 0,
            'posts' => 0, 'terms' => 0, 'samples' => array(),
        );

        // Group raw meta rows by object id, keeping both ACF keys so we can apply
        // the front-end priority: sponlineitemid wins over sponsorship_id.
        $group = function( $rows ) {
            $by = array();
            if ( is_array( $rows ) ) {
                foreach ( $rows as $r ) {
                    $by[ (int) $r->obj_id ][ $r->meta_key ] = $r->meta_value;
                }
            }
            return $by;
        };

        // --- Posts / pages ---
        // Join wp_posts to skip revisions, auto-drafts, and trash — ACF copies
        // field values onto revisions, which would otherwise inflate the count
        // massively and waste writes on non-served content.
        $post_cands = $group( $wpdb->get_results(
            "SELECT m.post_id AS obj_id, m.meta_key, m.meta_value
               FROM {$wpdb->postmeta} m
               INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
              WHERE m.meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND m.meta_value <> ''
                AND p.post_type <> 'revision'
                AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )"
        ) );
        // Bulk lookup of posts that already have a plugin value, so we don't run a
        // per-row get_post_meta() across thousands of posts (which can time out).
        $post_has = array();
        if ( $post_cands ) {
            $ids = implode( ',', array_map( 'intval', array_keys( $post_cands ) ) );
            foreach ( (array) $wpdb->get_col(
                "SELECT post_id FROM {$wpdb->postmeta}
                  WHERE meta_key = '_engam_v2_sponsor_id' AND meta_value <> '' AND post_id IN ( {$ids} )"
            ) as $had ) {
                $post_has[ (int) $had ] = true;
            }
        }
        foreach ( $post_cands as $pid => $vals ) {
            $acf_value = sanitize_text_field( $vals['sponlineitemid'] ?? $vals['sponsorship_id'] ?? '' );
            if ( $acf_value === '' ) continue;
            $result['candidates']++;
            if ( isset( $post_has[ (int) $pid ] ) ) { $result['skipped_existing']++; continue; }

            if ( $write ) update_post_meta( $pid, '_engam_v2_sponsor_id', $acf_value );
            $result['migrated']++;
            $result['posts']++;

            if ( count( $result['samples'] ) < 25 ) {
                $p = get_post( $pid );
                $result['samples'][] = array(
                    'kind'  => 'Post',
                    'title' => $p ? $p->post_title : '(unknown #' . $pid . ')',
                    'value' => $acf_value,
                    'meta'  => $p ? $p->post_type : '',
                    'edit'  => get_edit_post_link( $pid ),
                );
            }
        }

        // --- Categories / tags / other taxonomies (no revisions to worry about) ---
        $term_cands = $group( $wpdb->get_results(
            "SELECT term_id AS obj_id, meta_key, meta_value
               FROM {$wpdb->termmeta}
              WHERE meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND meta_value <> ''"
        ) );
        $term_has = array();
        if ( $term_cands ) {
            $ids = implode( ',', array_map( 'intval', array_keys( $term_cands ) ) );
            foreach ( (array) $wpdb->get_col(
                "SELECT term_id FROM {$wpdb->termmeta}
                  WHERE meta_key = '_engam_v2_sponsor_id' AND meta_value <> '' AND term_id IN ( {$ids} )"
            ) as $had ) {
                $term_has[ (int) $had ] = true;
            }
        }
        foreach ( $term_cands as $tid => $vals ) {
            $acf_value = sanitize_text_field( $vals['sponlineitemid'] ?? $vals['sponsorship_id'] ?? '' );
            if ( $acf_value === '' ) continue;
            $result['candidates']++;
            if ( isset( $term_has[ (int) $tid ] ) ) { $result['skipped_existing']++; continue; }

            if ( $write ) update_term_meta( $tid, '_engam_v2_sponsor_id', $acf_value );
            $result['migrated']++;
            $result['terms']++;

            if ( count( $result['samples'] ) < 25 ) {
                $t = get_term( $tid );
                $valid = ( $t && ! is_wp_error( $t ) );
                $tax_obj = $valid ? get_taxonomy( $t->taxonomy ) : null;
                $result['samples'][] = array(
                    'kind'  => 'Term',
                    'title' => $valid ? $t->name : '(unknown term #' . $tid . ')',
                    'value' => $acf_value,
                    'meta'  => $tax_obj ? $tax_obj->labels->singular_name : ( $valid ? $t->taxonomy : '' ),
                    'edit'  => $valid ? get_edit_term_link( $tid, $t->taxonomy ) : '',
                );
            }
        }

        return $result;
    }
}
