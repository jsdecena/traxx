{*
* 2007-2013 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<p class="payment_module">
	<a href="javascript:void(0)" onclick="$('#traxxForm').submit();" id="process_payment" title="{l s='Pay by Credit Card' mod='traxx'}">
		<img src="{$this_path_bw}img/payment-logo.jpg" alt="{l s='Pay by Credit Card' mod='traxx'}" width="80" height="40"/>
		{l s='Pay by Credit Card' mod='traxx'}
	</a>
</p>

<form action="https://secure.traxx.asia/gateway/index.html" method="post" id="traxxForm">
	{foreach from=$post_data key=k item=v}
		<input type="hidden" name="{$k}" value="{$v|htmlspecialchars|escape:'htmlall':'UTF-8'}" />
	{/foreach}
</form>