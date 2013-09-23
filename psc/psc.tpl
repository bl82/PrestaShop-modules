<p class="payment_module">
	<a href="javascript:$('#psc_form').submit();" title="{$sitetitle}">
		<img src="{$module_template_dir}{$logo}.gif" alt="{$sitetitle}" />
		{$sitetitle}
	</a>
</p>

<form action="{$pscUrl}" method="post" id="psc_form" class="hidden">
	<input type="hidden" name="site" value="{$site}" />
	<input type="hidden" name="montant" value="{$montant}" />
	<input type="hidden" name="devise" value="{$currency->iso_code}" />
	<input type="hidden" name="test" value="{$test}" />
	<input type="hidden" name="debug" value="{$debug}" />
	<input type="hidden" name="ref" value="{$ref}" />
	<input type="hidden" name="divers" value="{$divers}" />
	<input type="hidden" name="bn" value="PRESTASHOP_WPS" />
</form>