<?php
/**
 * Template for the "event_section" loop template for the [ESPRESSO_MY_EVENTS] shortcode
 * Available template args:
 *
 * @type    string     $object_type        The type of object for objects in the 'object' array. It's expected for this
 *          template that the type is 'Event'
 * @type    EE_Event[] $objects
 * @type    int        $object_count       Total count of all objects
 * @type    string     $your_events_title  The default label for the Events section
 * @type    string     $your_tickets_title The default label for the Tickets section
 * @type    string     $template_slug      The slug for the template.  For this template it will be 'simple_list_table'
 * @type    int        $per_page           What items are shown per page
 * @type    string     $path_to_template   The full path to this template
 * @type    int        $page               What the current page is (for the paging html).
 * @type    string     $with_wrapper       Whether to include the wrapper containers or not.
 * @type    int        $attr_id             Attendee ID all the displayed data belongs to.
 */
foreach ($objects as $object) 
    $object_count = $object_count + $object['object_count'];
$url             = EEH_URL::current_url();
$pagination_html = EEH_Template::get_paging_html(
    $object_count,
    $page,
    $per_page,
    $url,
    false,
    'ee_mye_page',
    array(
        'single' => __('event', 'event_espresso'),
        'plural' => __('events', 'event_espresso'),
    )
);
?>
<?php
if($object_count > 0): ?>
<div class="espresso-my-events <?php echo $template_slug; ?>_container">
<h3><?php echo $your_events_title; ?></h3>
<div class="espresso-my-events-inner-content">
<?php endif;

foreach ($objects as $object) :

if ($with_wrapper) : ?>   
    <?php do_action('AHEE__loop-espresso_my_events__before', $object_type, $object['objects'], $template_slug,  $object['att_id']); ?>
    
<?php
endif;
// $with_wrapper check ?>
<?php

if ($object) : 

    $attendee = EEM_Attendee::instance()->get_one_by_ID($object['att_id']);
?>
    <span style="font-family: mathone;color: #101D51; font-weight:normal; font-size:18px;"><?php echo  $attendee->full_name()?> </span> <br>
    <div class="course_table_list">
    <table class="espresso-my-events-table <?php echo $template_slug; ?>_table">
        <thead class="espresso-table-header-row">
        <tr>
            <td scope="col" class="espresso-my-events-event-th">
               <span class="self"> <?php echo apply_filters(
                    'FHEE__loop-espresso_my_events__table_header_event',
                    esc_html__('Title', 'event_espresso'),
                    $object_type,
                    $object['objects'],
                    $template_slug,
                    $object['att_id']
                ); ?> </span>
            </td>
            <td scope="col" class="espresso-my-events-location-th">
                <span class="self">  <?php echo apply_filters(
                        'FHEE__loop-espresso_my_events__location_table_header',
                        esc_html__('Location', 'event_espresso'),
                        $object_type,
                        $object['objects'],
                        $template_slug,
                        $object['att_id']
                    ); ?>
                </td></span>
            <td scope="col" class="espresso-my-events-datetime-range-th">
            <span class="self"> <?php echo apply_filters(
                    'FHEE__loop-espresso_my_events__datetime_range_table_header',
                    esc_html__('When', 'event_espresso'),
                    $object_type,
                    $object['objects'],
                    $template_slug,
                    $object['att_id']
                ); ?></span>
            </td>
            <td scope="col" class="espresso-my-events-tickets-num-th">
            <span class="self"> <?php echo apply_filters(
                    'FHEE__loop-espresso_my_events__tickets_num_table_header',
                    esc_html__('Tickets', 'event_espresso'),
                    $object_type,
                    $object['objects'],
                    $template_slug,
                    $object['att_id']
                ); echo "&nbsp;"; ?></span>
            </td>
            <td scope="col" class="espresso-my-events-actions-th">
            <span class="self">  <?php echo apply_filters(
                    'FHEE__loop-espresso_my_events__actions_table_header',
                    esc_html__('Actions', 'event_espresso'),
                    $object_type,
                    $object['objects'],
                    $template_slug,
                    $object['att_id']
                ); ?></span>
            </td>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($object['objects'] as $objectitem) :
            if (! $objectitem instanceof EE_Event) {
                continue;
            }
            $att_id = $object['att_id'];
            $template_args = array('event'              => $objectitem,
                                   'your_tickets_title' => $your_tickets_title,
                                   'att_id'             => $att_id,
            );
            $template      = 'content-espresso_my_events-event_section.template.php';
            EEH_Template::locate_template($template, $template_args, true, false);
            ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php else : ?>
    <div class="no-events-container">
        <p>
            <?php echo apply_filters(
                'FHEE__loop-espresso_my_events__no_events_message',
                esc_html__('You have no events yet', 'event_espresso'),
                $object_type,
                $object,
                $template_slug,
                $object['att_id']
            ); ?>
        </p>
    </div>
<?php endif; endforeach;?>
<div class="espresso-my-events-footer">
        <div class="espresso-my-events-pagination-container <?php echo $template_slug; ?>-pagination">
            <span class="spinner"></span>
            <?php echo $pagination_html; ?>
            <div style="clear:both"></div>
        </div>
        <div style="clear:both"></div>
        <?php EEH_Template::locate_template(
            'status-legend-espresso_my_events.template.php',
            array('template_slug' => $template_slug),
            true,
            false
        ); ?>
    </div>
<?php
if ($with_wrapper) : ?>
    </div>
    <?php do_action('AHEE__loop-espresso_my_events__after', $object_type, $object['objects'], $template_slug, $object['att_id']); ?>
    </div>
<?php
endif;
// end $wrapper check
