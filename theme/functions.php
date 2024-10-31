<?php

function osa_render_meta( $data, $property, $label = null, $key = null ) {
    if ( $data == null || $property == null) {
        return;
    }

    $field = get_post_meta( $data, $property, true );

    if ( $field == null ||
    ( is_object( $field ) && !isset( $field->{$key} ) ) ||
    ( is_array( $field ) && sizeof( $field ) < 1 ) ) {
        return;
    }

    $result = '';

    if ( $label != null ) {
        $result = sprintf( '<div class="osa-meta-label osa-meta-label-%s">%s:</div>', $property, $label );
    }

    if ( is_array( $field) ) {
        $result = $result . sprintf( '<div class="osa-meta-content osa-meta-content-%s">', $property );

        if ( $key != null) {
            $elements = array_map(function( $e ) use ( $key ) {
                return $e->{$key}; }, $field );
            $result = $result . implode(', ', $elements);
        } else {
            $result = $result . implode(', ', $field);
        }

        $result = $result . '</div>';

    } elseif ( is_object( $field ) ) {
        $result = $result . sprintf( '<div class="osa-meta-content osa-meta-content-%s">%s</div>', $property, $field->{$key} );
    } else {
        $result = $result . sprintf( '<div class="osa-meta-content osa-meta-content-%s">%s</div>', $property, $field );
    }

    echo $result;
}

function osa_render_date( $data, $property, $label = null ) {
    if ( $data == null || $property == null) {
        return;
    }

    $field = get_post_meta( $data, $property, true );

    if ( $field == null || !is_array( $field ) ||
    ( is_array( $field ) && sizeof( $field ) < 1 ) ) {
        return;
    }

    $result = '';

    if ( $label != null ) {
        $result = sprintf( '<div class="osa-meta-label osa-meta-label-%s">%s:</div>', $property, $label );
    }

    $result = $result . sprintf( '<div class="osa-meta-content osa-meta-content-%s">', $property );
    $isNext = false;

    foreach( $field as $date ) {
        if ( is_object( $date ) ) {
            if ( $isNext ) {
                $result .= ', ';
            } else {
                $isNext = true;
            }
            if ( isset( $date->dateFrom )) {
                $result .= $date->dateFrom;
            }
            if ( isset( $date->dateTo )) {
                $result .= ' - ' . $date->dateTo;
            }
        }
    }

    $result = $result . '</div>';

    echo $result;
}

function osa_render_link( $data, $property, $label = null ) {
    if ( $data == null || $property == null) {
        return;
    }

    $field = get_post_meta( $data, $property, true );

    if ( $field == null || is_array( $field ) ||  is_object( $field ) ) {
        return;
    }

    $result = '';

    if ( $label != null ) {
        $result = sprintf( '<div class="osa-meta-label osa-meta-label-%s">%s:</div>', $property, $label );
    }

    $result = $result . sprintf( '<div class="osa-meta-content osa-meta-content-%s"><a href="http://%s">%s</a></div>', $property, $field, $field );

    echo $result;
}

function osa_render_attachments( $post_id, $label = null, $size = "thumbnail", $icon = TRUE ) {
    $attachments_params = array(
        'post_parent' => $post_id,
        'post_type' => 'attachment'
    );

    $attachments_array = get_children( $attachments_params );

    $result = '';

    if ( !empty( $attachments_array ) ) {
        if ( $label != null ) {
            $result = $result . sprintf( '<div class="osa-file-gallery-label">%s:</div>', $label );
        }
        $result = $result . '<ul class="osa-file-gallery">';
        foreach ( $attachments_array as $attachment ) {
            if ( strpos( $attachment->post_mime_type, 'image' ) !== FALSE ) {
                $result = $result . sprintf( '<a href="%s" target="_blank">%s</a>', $attachment->guid, wp_get_attachment_image( $attachment->ID, 'thumbnail', true, array("alt" => $attachment->post_mime_type)  ) );
            } elseif ( strpos( $attachment->post_mime_type, 'pdf' ) !== FALSE ) {
                $result = $result . sprintf( '<a href="%s" target="_blank">%s</a>', $attachment->guid, wp_get_attachment_image( $attachment->ID, 'thumbnail', true, array("alt" => $attachment->post_mime_type) ) );
            } elseif ( strpos( $attachment->post_mime_type, 'audio' ) !== FALSE) {
                $result = $result . sprintf( '<audio controls><source src="%s" type="audio/mpeg">Twoja przeglądarka nie obsługuje formatu audio.</audio>', $attachment->guid, wp_get_attachment_url( $attachment->ID ) );
            } elseif ( strpos( $attachment->post_mime_type, 'video' ) !== FALSE) {
                $result = $result . sprintf( ' <video width="320" controls><source src="%s" type="video/mp4"><source src="%s" type="video/ogg">Your browser does not support the video tag.</video>', $attachment->guid, wp_get_attachment_url( $attachment->ID ) );
            } else {
                $result = $result . sprintf( '<a href="%s" target="_blank">%s</a>', $attachment->guid, wp_get_attachment_image( $attachment->ID, 'thumbnail', true, array("alt" => $attachment->post_mime_type) ) );
            }
        }
        $result = $result . '</ul>';
    }

    echo $result;
}

function osa_render_children( $post_id, $post_type, $label = null ) {
    $children_params = array(
        'post_parent' => $post_id,
        'post_type' => $post_type
    );

    $children_array = get_children( $children_params );
    $result = '';

    if ( !empty( $children_array ) ) {
        if ( $label != null ) {
            $result = $result . sprintf( '<div class="osa-children-label">%s:</div>', $label );
        }
        $result = $result . '<ul class="osa-children">';
        foreach ( $children_array as $child ) {
            $result = $result . sprintf( '<li class="osa-child-%s"><a href="%s">%s</a></li>', $post_type, $child->guid, $child->post_title );
        }
        $result = $result . '</ul>';
    }

    echo $result;
}