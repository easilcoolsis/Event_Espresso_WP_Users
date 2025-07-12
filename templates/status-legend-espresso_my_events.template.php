<?php
/**
 * Template for the "status legend" box for the [ESPRESSO_MY_EVENTS] shortcode output.
 * Available template args:
 *
 * @type    string $template_slug The slug for the called template. eg. 'simple_list_table', or 'event_section'.
 */
$reg_statuses = EEM_Registration::reg_status_array(array(), true);
$per_col      = 5;
$count        = 1;

// let's setup the legend items
$items = array();
foreach ($reg_statuses as $status_code => $status_label) {

    $status_label = sprintf(esc_html__('%s Registration', 'event_espresso'), $status_label);

    $items[ $status_code ] = array(
        'class' => 'ee-status-legend-box ee-status-' . $status_code,
        'desc'  => $status_label,
    );
}


?>
<div class="espresso-my-events-legend-container">
    <dl class="espresso-my-events-legend-list">
        <?php foreach ($items as $item => $details) : ?>
        <?php if ($per_col < $count) : ?>
    </dl>
    <dl class="espresso-my-events-legend-list">
        <?php $count = 1;
        endif; ?>
        <dt class="ee-legend-item-<?php echo $item; ?>">
            <?php $class = ! empty($details['class']) ? $details['class'] : 'ee-legend-no-class'; ?>
            <span class="<?php echo $class; ?>"></span>
            <span class="ee-legend-description"><?php echo $details['desc']; ?></span>
            </dt>
        <?php $count++;
        endforeach; ?>
    </dl>
</div>