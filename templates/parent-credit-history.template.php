<?php

  $history_recs = EE_WPUsers::get_parent_credit_history(get_current_user_id());
  if(count($history_recs) > 0) :
?>
<br><br><br>
<br><br><br>
<span id="parent-credit-history" style="font-family: mathone;color: #101D51; margin-left: 5%;font-weight:normal; font-size:18px;">Credit History </span> <br><br>
<div class="course_table_list">

    <table class="espresso-my-events-table">
        <thead class="espresso-table-header-row">
        <tr>

         <td scope="col" class="espresso-my-events-event-th">
               <span class="self">Description</span>
         </td>
         <td scope="col" class="espresso-my-events-event-th">
               <span class="self">Add/Remove</span>
         </td>
         <td scope="col" class="espresso-my-events-event-th">
               <span class="self">Course Price</span>
         </td>
         <td scope="col" class="espresso-my-events-event-th">
               <span class="self"> Credit Amount</span>
         </td>
         <td scope="col" class="espresso-my-events-event-th">
               <span class="self">Date</span>
         </td>
  
        </tr>
        </thead>
        <tbody>
         <?php foreach ($history_recs as $index => $history_rec) {?>
        <tr class="ee-my-events-event-section-summary-row">

            <td>
              <?php echo $history_rec["description"];?>
            </td>
            <td>
              <?php if( $history_rec["credit_debit"] == 'C')
                      echo "Remove";
                      else echo "Add"; ?>
            </td>
            <td>
              <?php if($history_rec["course_price"] > 0) echo '$'.$history_rec["course_price"];
                    else echo "-"  ?>
            </td>
            <td>
              <?php echo '$'.$history_rec["credit_amount"]; ?>
            </td>
            <td>
              <?php echo  $history_rec["create_date"]; ?>
            </td>
        </tr>
        <?php }?>
        </tbody>
</table>
</div>

<?php endif;?>




