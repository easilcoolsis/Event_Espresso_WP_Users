<?php
/**
 * Template for the "event_section" content template for each ticket/registration row via [ESPRESSO_MY_EVENTS] shortcode
 * Available template args:
 *
 * @type $registration EE_Registration registration object
 */
$ticket = $registration->ticket();
?>
<tr>
    <td class="ee-status-strip reg-status-<?php echo $registration->status_ID(); ?>"></td>
    <td>
        <?php echo $ticket instanceof EE_Ticket ? $ticket->name() : ''; ?>
    </td>
    <?php
    //     $category = get_post_meta($registration->event_ID(), 'category', true);
    //     $duration = ' + 1 years';
    //      $duration = ' + 2 years';        
    //    if($category === 'self')
    //      $date_format = get_option( 'date_format' );
    //     $startdate = strtotime($registration->date());
    //     $enddate = strtotime($registration->date(). $duration);
    //     echo date_i18n(  $date_format , $startdate ) . "-" . date_i18n(  $date_format , $enddate );        
    //     ?>
    
    <td>
        <?php
        $actions = array();
        $link_to_view_invoice_text = esc_html__('Link to view invoice', 'event_espresso');
    

        // invoice link?
        if ($registration->invoice_url()) {
            $actions['invoice'] = '<a aria-label="' . $link_to_view_invoice_text
                                  . '" title="' . $link_to_view_invoice_text
                                  . '" href="' . $registration->invoice_url() . '">'
                                  . '<span class="dashicons dashicons-media-spreadsheet ee-icon-size-18"></span>View Invoice</a>';
        }

        // filter actions
        $actions = apply_filters(
            'FHEE__EES_Espresso_My_Events__actions',
            $actions,
            $registration
        );

        // ...and echo the actions!
        echo implode('&nbsp;', $actions);
        ?>
    </td>
</tr>