<?php

class MiembroPressCartGeneric extends MiembroPressCart {
	function instructions() {
		global $miembropress; ?>
		<h3>Automatic Registration</h3>
         <p>You can either use MiembroPress to build a list of free members or charge them for one-time access.</p>
         <p>Just make sure to set your thank you URL of your payment button to the level where you want to provide access:</p>
         <p><table class="widefat" style="width:400px;">
         <thead>
         <tr>
            <th scope="col" style="text-align:center;"><nobr>Level Name</nobr></th>
            <th scope="col">Thank You URL / Return URL</th>
         </tr>
         </thead>
         <?php foreach ($miembropress->model->getLevels() as $level): ?>
         <tr>
            <td style="text-align:center;"><?php echo htmlentities($level->level_name); ?></td>
            <td><a href="<?php echo $miembropress->model->signupURL($level->level_hash); ?>" target="_blank"><?php echo $miembropress->model->signupURL($level->level_hash); ?></a></td>
         <?php endforeach; ?>
         </table></p>

         <p>(<b>Note:</b> It is HIGHLY suggested that if you click this link, you instead Right-Click and choose the <code>Open in Incognito Window</code> option.</p>

         <blockquote>
         <p>For example, to take PayPal payments using a BUSINESS (not a Personal or Premier account), complete the following steps:</p>

         <ol>
            <li>Login to <a target="_blank" href="https://www.paypal.com">PayPal.com</a> and click the &quot;Merchant Services&quot; tab</li>
            <li>Click &quot;Create payment buttons for your website&quot;</li>
            <li>Choose to create a &quot;Buy Now&quot; button</li>
            <li>Type in the &quot;Item Name&quot; to be the name of your product (i.e. <code><?php echo get_option("blogname"); ?></code>, and choose the &quot;Price&quot; such as <code>7.00</code></li>
            <li>Under Step 2, be sure <code>Save button at PayPal</code> is checked</li>
            <li>Scroll down to Step 3 (Customize advanced features)</li>
            <li>Be sure to uncheck &quot;Take customers to this URL when they cancel their checkout&quot;</li>

            <li>Check the &quot;Take customers to this URL when they finish checkout&quot; box and set it to the level you want: <code><?php echo $url; ?></code></li>

            <p><table class="widefat" style="width:400px;">
            <thead>
            <tr>
               <th scope="col" style="text-align:center;"><nobr>Level Name</nobr></th>
               <th scope="col">Thank You URL / Return URL</th>
            </tr>
            </thead>

            <?php foreach ($miembropress->model->getLevels() as $level): ?>
            <tr>
               <td style="text-align:center;"><?php echo htmlentities($level->level_name); ?></td>
               <td><a href="<?php echo $miembropress->model->signupURL($level->level_hash); ?>" target="_blank"><?php echo $miembropress->model->signupURL($level->level_hash); ?></a></td>
            <?php endforeach; ?>
            </table></p>

            <li>Click &quot;Create Button&quot;</li>
            <li>Click the &quot;Email&quot; tab and grab your payment button, such as <code>https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=123</code>
         </ol>
         </blockquote>

         <p>You can now either direct link to this button or use a sales letter template such as <a target="_blank" href="http://www.papertemplate.com">Paper Template</a> to host your sales letter and display your payment button.</p>

         <h3>Manual Registration</h3>

         <p>When activated, MiembroPress will protect all the pages and posts on your blog. Once ANY WordPress users logs in, they will get access to all the content on your blog.</p>

         <p>This means you can manually <a href="user-new.php">add new users</a> to your site at any time.</p>

         <p>You could also great a &quot;catch-all&quot; user, for example, <a href="user-new.php">create a new user</a> with username &quot;secret&quot; and password &quot;secret&quot; and give this for ALL your members to share the same login information.</p>
      <?php
	}
}

?>