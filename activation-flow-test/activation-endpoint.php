<?php
if ( isset( $_GET['codeChallenge'] ) && isset( $_GET['redirectUrl'] ) ) {
	$redirectUrl = $_GET['redirectUrl'];
	?>
	<style>
		#activate-btn {
			position: fixed;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			padding: 20px 25px;
			background-color: #000;
			color: #fff;
			border: none;
			border-radius: 8px;
			text-decoration: none;
			font-size: 1.2em;
			font-family:'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif
		}
	</style>
	<a class="button button-primary" href="<?php echo $redirectUrl; ?>" id="activate-btn">Activate account</a>
	<script>
		document.getElementById('activate-btn').addEventListener('click', function() {
			if (window.opener) {
				window.opener.postMessage({ param: 'true' }, 'sample-url.php' );
			}
			window.close();
		});
	</script>
	<?php
}
