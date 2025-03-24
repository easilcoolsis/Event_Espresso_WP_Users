<?php
/**
 * Template for the "event_section" content template for the [ESPRESSO_MY_EVENTS] shortcode
 * Available template args:
 *
 * @type    EE_Event $event              event object
 * @type    string   $your_tickets_title title for the ticket.
 * @type    int      $att_id             The id of the EE_Attendee related to the displayed data.
 */
$registrations = $event->get_many_related('Registration', array(array('ATT_ID' => $att_id)));
$reg_statuses = EEM_Registration::reg_status_array(array(), true);

 $approved = array_filter($registrations, function($registration) {
        return $registration->status_ID() == 'RAP';
 });
 $pending = array_filter($registrations, function($registration) {
    return $registration->status_ID() == 'RPP';
});
$waiting = array_filter($registrations, function($registration) {
    return $registration->status_ID() == 'RNA';
});
$declined = array_filter($registrations, function($registration) {
    return $registration->status_ID() == 'RDC';
});
$incomplete = array_filter($registrations, function($registration) {
    return $registration->status_ID() == 'RIC';
});
$cancelled = array_filter($registrations, function($registration) {
    return $registration->status_ID() == 'RCN';
});

 $status  = '';
 if (count($approved) > 0)
 {
    $status_code = 'RAP';
 }
 else if (count($pending) > 0)
 {
    $status_code = 'RPP';
 }
 else if (count($waiting) > 0)
 {
    $status_code = 'RNA';
 }
 else if (count($incomplete) > 0)
 {
    $status_code = 'RIC';
 }
 else if (count($cancelled) > 0)
 {
    $status_code = 'RCN';
 }
 else if (count($pending) > 0)
 {
    $status_code = 'RPP';
 }

 $status = $reg_statuses[$status_code];
     
?>
<tr class="ee-my-events-event-section-summary-row">

    <td class="ee-status-strip reg-status-<?php echo $status_code; ?>"></td>
    <td class="reg-status-<?php echo $status_code; ?>">
        <?php echo $status; ?>
    </td>
    <td>
        <a aria-label="<?php printf(esc_html__('Link to %s', 'event_espresso'), $event->name()); ?>"
           href="<?php echo esc_url_raw(get_permalink($event->ID())); ?>"
        >
           <?php echo $event->name(); ?>
        </a>
    </td>
    <td>
        <?php
        $venues        = $event->venues();
        $venue_content = array();
        foreach ($venues as $venue) :
            $venue_content[] = '
            <a aria-label="' . sprintf(esc_html__('Link to %s', 'event_espresso'), $venue->name() ) . '"
                href="' . esc_url_raw(get_permalink($venue->ID())) . '">
                ' . $venue->name() . '
            </a>';
        endforeach;
        echo implode('<br>', $venue_content);
        ?>
    </td>
    <td>
        <?php 
        	$category = get_post_meta($event->ID(), 'category', true);
            if($category === 'self')
              echo "2 years from enrollment";
                
            else {  
               $days = explode(' - ', espresso_event_date_range('', '', '', '', $event->ID(), false));
               $daysrange = '';
               foreach($days as  $key =>$day)
               {
                $daysrange = $daysrange . substr($day, 0, 10);
                if($key === 0)
                  $daysrange = $daysrange . '-';
               }
               echo $daysrange;
            }
               ?>
    </td>
    <td>
    <?php 
      $days_and_times = get_post_meta($event->ID(), 'days_times', true);
       echo $days_and_times;
      ?>

    </td>

    <td>
        <?php echo count($registrations); ?>
    </td>
    <td>
        <span class="dashicons dashicons-admin-generic js-ee-my-events-toggle-details"></span>
    </td>
</tr>
<tr class="ee-my-events-event-section-details-row">
    <td colspan="7">
        <div class="ee-my-events-event-section-details-inner-container">
            <section class="ee-my-events-event-section-details-event-description">
                <div class="ee-my-events-right-container">
                    <span class="dashicons dashicons-admin-generic js-ee-my-events-toggle-details"></span>
                </div>
                <h3><?php echo $event->name(); ?></h3>
                <?php
                /**
                 * There is a ticket for EE core: https://events.codebasehq.com/projects/event-espresso/tickets/8405
                 * that hopefully will remove the necessity for the apply_filters() here.
                 */
                ?>
                <?php echo apply_filters('the_content', $event->description()); ?>
            </section>
            
            <section class="ee-my-events-event-section-tickets-list-table-container">
                <?php if ($registrations) : ?>
                    <table class="espresso-my-events-table simple-list-table">
                        <thead>
                        <tr>
                            <th scope="col" class="espresso-my-events-reg-status ee-status-strip">
                            </th>
                            <th scope="col" >
                               <?php echo 'Status' ?>
                            </th>
                            <th scope="col" class="espresso-my-events-ticket-th">
                                <?php echo apply_filters(
                                    'FHEE__content-espresso_my_events__table_header_ticket',
                                    esc_html__('Registration', 'event_espresso'),
                                    $event
                                ); ?>
                            </th>               
                            <th scope="col" class="espresso-my-events-actions-th">
                                <?php echo apply_filters(
                                    'FHEE__content-espresso_my_events__actions_table_header',
                                    esc_html__('Invoice', 'event_espresso'),
                                    $event
                                ); ?>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($registrations as $registration) {
                            if (! $registration instanceof EE_Registration) {
                                continue;
                            }
                            $template_args = array('registration' => $registration );
                            $template_args['status'] = $status;
                            $template      = 'content-espresso_my_events-event_section_tickets.template.php';
                            EEH_Template::locate_template($template, $template_args, true, false);
                        }
                        ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="no-tickets-container">
                        <p>
                            <?php echo apply_filters(
                                'FHEE__content-espresso_my_events-no_tickets_message',
                                esc_html__('You have no tickets for this event', 'event_espresso'),
                                $event
                            ); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </td>
</tr>
