(function ($) {
  $(function () {

    //WC BLOCKS ANTI NOTICE DUPLICATES

    const uniqueElements = {};

    const methodBoxElements = document.querySelectorAll('#wc-blocks-notice-1');

    removeDuplicateElements(methodBoxElements);

    // Form invalidate
    let formIsDirty = false
    $('.woocommerce-input-wrapper').on('change', function () {
      formIsDirty = true
      const timeoutHandler = setInterval(function () {
        if (formIsDirty) {
          $('body').trigger('update_checkout')
          formIsDirty = false
        } else {
          clearInterval(timeoutHandler)
        }
      }, 500)
    })
    const collectTimer = setInterval(function () {
      let methodInputs = $('input[name="iihtcg_selector_input"]')
      selectionMade = false
      $.each(methodInputs, function (i, input) {
        if (input.checked === true) {
          selectionMade = input.value
        }
      })
    }, 500)
    // Shipping override for iihealth
    const methodBoxEnabled = $('#iihtcg_selector')
    if (methodBoxEnabled.length) {
      jQuery(document.body).on('updated_checkout', function () {
        jQuery('*[id*=local_pickup]').each(function () {
          if (selectionMade == 'tcg') {
            jQuery(this).parent().hide()
          } else {
            jQuery(this).parent().show()
          }
        })
      })
      // Get the billing details field and surround it with a div
      const wbf = $('div.woocommerce-billing-fields')
      const wbfParent = wbf.parent()
      const wbfOverlay = document.createElement('div')
      wbfOverlay.setAttribute('id', 'woocommerce_billing_field_overlay')
      wbfOverlay.style.opacity = '20%'
      wbfOverlay.style.zIndex = '999'
      wbf.replaceWith(wbfOverlay)
      wbfOverlay.append(wbf[0])
      // Get the order review field and surround it with a div
      const orf = $('#order_review')
      const orfParent = orf.parent()
      const orfOverlay = document.createElement('div')
      orfOverlay.setAttribute('id', 'order_review_field_overlay')
      orfOverlay.style.opacity = '20%'
      orfOverlay.style.zIndex = '999'
      orf.replaceWith(orfOverlay)
      orfOverlay.append(orf[0])
      const iihtcgSelectors = $('input[name="iihtcg_selector_input"]')
      let selectionMade = true
      showHideShipping(selectionMade)
      $('#iihtcg_method_field').hide()
      jQuery('article .woocommerce-checkout').show()
      if (typeof iihtcgSelectors != 'undefined') {
        $.each(iihtcgSelectors, function (i, selector) {
          if (selector.checked === true) {
            selectionMade = selector.value
            showHideShipping(selectionMade)
          } else {
            showHideShipping(false)
          }
        })
        iihtcgSelectors.on('change', function (e) {
          if (this.checked === true) {
            selectionMade = this.value
            showHideShipping(selectionMade)
          }
          $('body').trigger('update_checkout')
          jQuery('#billing_state').selectWoo()
        })
      }
      // Hide extra boxes created by moving the div and select2
      setTimeout(function () {
        const countries = $('span[aria-labelledby="select2-billing_country-container"]')
        if (countries && countries.length > 1) {
          countries[1].style.display = 'none'
        }
        const states = $('span[aria-labelledby="select2-billing_state-container"]')
        if (states && states.length > 1) {
          states[1].style.display = 'none'
        }
        const tcgBillingPlaces = $('span[aria-labelledby="select2-billing_tcg_place_lookup-container"]')
        if (tcgBillingPlaces && tcgBillingPlaces.length > 1) {
          tcgBillingPlaces[1].style.display = 'none'
        }
      }, 2000)
      select2LocationSelect()

      function recreateProvinceSelect2OnLoad() {
        jQuery('#select2-billing_state-container').parent().parent().remove()
        jQuery('#billing_state').selectWoo()
        jQuery('#billing_state').selectWoo()
      }

      recreateProvinceSelect2OnLoad()

      const deliverToDifferentAddress = $('h3#ship-to-different-address')

      function hideDeliverToDifferentAddress() {
        if (deliverToDifferentAddress.length) {
          deliverToDifferentAddress.hide()
        }
      }

      hideDeliverToDifferentAddress()
      $('input[name="iihtcg_selector_input"]').on('change', function () {
        const lookupPlaceLabelSelector = $('input[name="billing_tcg_place_lookup_place_label"]')
        const proceedToPaymentButtonSelector = $('a:contains(\'Proceed to payment\')')
        if ($(this).val() === 'collect') {
          proceedToPaymentButtonSelector.show()
          hideDeliverToDifferentAddress()
        } else {
          deliverToDifferentAddress.show()
          addAsteriskSuburbCity()
          if ((typeof lookupPlaceLabelSelector.val() === 'undefined') || lookupPlaceLabelSelector.val().trim() === '') {
            proceedToPaymentButtonSelector.hide()
          }
        }
      })
    }

    function removeDuplicateElements(allElements) {
      allElements.forEach((element) => {
        // Step 4: Check for duplicates
        const id = element.id;
        if (id) {
          if (uniqueElements[id]) {
            // Duplicate found, remove the element
            element.remove();
          } else {
            // No duplicate found, mark this element as unique
            uniqueElements[id] = true;
          }
        }
      });
    }

    function fieldsValid() {
      let valid = true
      const inputValidationRequired = $('.validate-required input')
      const selectValidationRequired = $('.validate-required select')
      $.each(inputValidationRequired, function (i, val) {
        if (val.id.substr(0, 7) === 'billing' && val.value === '') {
          valid = false
        }
      })
      $.each(selectValidationRequired, function (i, val) {
        if (val.id.substr(0, 7) === 'billing' && val.value === '' && val.id.search('tcg_place_lookup') === -1) {
          valid = false
        }
      })
      return valid
    }

    function showHideShipping(selectionMade) {
      const wbfOverlay = $('#woocommerce_billing_field_overlay')
      const orfOverlay = $('#order_review_field_overlay')
      const selectBilling = $('#billing_tcg_place_lookup_field')
      const select2Container = $('span.select2-container--open')
      if (!selectionMade) {
        $('#iihtcg_method').val('none')
        selectBilling.hide()
        select2Container.hide()
        disableAllFieldsExceptShippingMethod()
        return
      }
      if (selectionMade === 'collect') {
        $('#order_review').show()
        $('#please_select').hide()
        $('#please_complete').show()
        wbfOverlay.css('opacity', '1')
        orfOverlay.css('opacity', '1')
        $('#iihtcg_method').val('collect')
        selectBilling.hide()
        select2Container.hide()
        enableAllFieldsExceptMethod()
        return
      }
      if (selectionMade === 'tcg') {
        $('#order_review').show()
        $('#please_select').hide()
        wbfOverlay.css('opacity', '1')
        orfOverlay.css('opacity', '1')
        $('#iihtcg_method').val('tcg')
        selectBilling.show()
        select2LocationSelect()
        enableAllFieldsExceptMethod()

      }
    }

    //disable all input fields except method
    function disableAllFieldsExceptShippingMethod() {
      if (jQuery('[name=iihtcg_selector_input]').length) {
        jQuery(':input').not('[name=iihtcg_selector_input]').prop('disabled', true)
      }
    }

    disableAllFieldsExceptShippingMethod()

    //enable all input fields except shipping method (which is already enabled)
    function enableAllFieldsExceptMethod() {
      if (jQuery('[name=iihtcg_selector_input]').length) {
        jQuery(':input').not('[name=iihtcg_selector_input]').prop('disabled', false)
      }
    }

    //cart
    $('body').on('click', '.shipping-calculator-button', function () {
      var cartShippingAreaPanel = $('#tcg-cart-shipping-area-panel')
      var shippingForm = cartShippingAreaPanel.prev('.woocommerce-shipping-calculator')
      var updateShippingButton = shippingForm.find('button[name=calc_shipping]')
      updateShippingButton.parent('p').before(cartShippingAreaPanel)
      cartShippingAreaPanel.show()
    })

    function select2LocationSelect() {
      var billstr = ''
      var suburbSelect
      var shipToDifferentPlace = $('#ship-to-different-address-checkbox').prop('checked')
      if (!shipToDifferentPlace) {
        suburbSelect = $('#billing_tcg_place_lookup_field select')
      } else {
        suburbSelect = $('#shipping_tcg_place_lookup_field select')
      }
      if (suburbSelect.length > 0) {
        suburbSelect.select2({
          placeholder: 'Start typing Suburb name...',
          minimumInputLength: 3,
          ajax: {
            url: theCourierGuy.url,
            dataType: 'json',
            delay: 350,
            data: function (term) {
              return {
                q: term, // search term
                action: 'wc_tcg_get_places'
              }
            },
            closeOnSelect: true,
            selectOnClose: true,
            processResults: function (data) {
              var results = []
              $.each(data, function (index, item) {
                results.push({
                  id: item.suburb_key,
                  text: item.suburb_value,
                  selected: (index === 0 && billstr.length > 0)
                })
              })
              return {
                results: results,
              }
            },
            cache: false
          }
        }).on('change', function (evt) {
          var select = $(this)
          var placeLabelInput = select.prev('input')
          var placeIdInput = placeLabelInput.prev('input')
          $(this).children().each(function () {
            var option = $(this)
            if (option.val() !== '') {
              placeIdInput.val(option.val())
              placeLabelInput.val(option.text())
            }
          })
          // Billing is required field so set if empty and alternate shipping is enabled
          if (select.attr('name') === 'shipping_tcg_place_lookup') {
            let billingSelect = $('#billing_tcg_place_lookup')
            let billingSelectOptions = $('#billing_tcg_place_lookup option')
            if (billingSelectOptions.length == 0 || (billingSelectOptions.length == 1 && billingSelectOptions[0].value == '')) {
              billingSelect.empty()
              $('input[name="billing_tcg_place_lookup_place_id"]').val(placeIdInput.val())
              $('input[name="billing_tcg_place_lookup_place_label"]').val(placeLabelInput.val())
              let opt = '<option value="' + placeIdInput.val() + '">' + placeLabelInput.val() + '</option>'
              billingSelect.append(opt)
            }
          }
          $('body').trigger('update_checkout')
        })
        if (!shipToDifferentPlace && billstr.length > 2) {
          suburbSelect.select2('open')
          $('input.select2-search__field').val(billstr)
          $('input.select2-search__field').trigger('input')
        }
      }
    }

    $(document.body).on('updated_cart_totals', function () {
      select2LocationSelect()
    })
    select2LocationSelect()
    $('.tcg-insurance').on('change', function () {
      $('.tcg-suburb-field select').trigger('change')
    })

    $("#shipping_insurance").change(function(){
      setShippingInsuranceValues();
    });

    $("#billing_insurance").change(function(){
      setBillingInsuranceValues();
    });

    function setBillingInsuranceValues() {
      if ($('#billing_insurance').prop('checked') === true) {
        $('#billing_insurance').val(1);
      } else {
        $('#billing_insurance').val(0);
      }
    }

    function setShippingInsuranceValues() {
      if ($("#shipping_insurance").prop('checked') === true) {
        $("#shipping_insurance").val(1);
      } else {
        $("#shipping_insurance").val(0);
      }
    }

    function clearPlaceSelects() {
      var placeSelects = $('.tcg-suburb-field').find('select')
      placeSelects.children('option').remove()
      placeSelects.val(null).trigger('change')
      $('input[name=billing_tcg_place_lookup_place_id]').val('')
      $('input[name=billing_tcg_place_lookup_place_label]').val('')
      $('input[name=shipping_tcg_place_lookup_place_id]').val('')
      $('input[name=shipping_tcg_place_lookup_place_label]').val('')
    }

    function toggleSuburbPanelDisplay(tcgSuburbSelect, tcgSuburbSelectPanel) {
      if (tcgSuburbSelect.val() === 'ZA') {
        if (theCourierGuy.southAfricaOnly === 'true') {
          tcgSuburbSelectPanel.show()
        }
      } else {
        if (theCourierGuy.southAfricaOnly === 'true') {
          tcgSuburbSelectPanel.hide()
        }
        clearPlaceSelects()
      }
    }

    $('#billing_country').on('change', function (event) {
      var tcgSuburbSelectPanel = $('#billing_tcg_place_lookup_field')
      toggleSuburbPanelDisplay($(this), tcgSuburbSelectPanel)
    })
    $('#shipping_country').on('change', function (event) {
      var tcgSuburbSelectPanel = $('#shipping_tcg_place_lookup_field')
      toggleSuburbPanelDisplay($(this), tcgSuburbSelectPanel)
    })
    if (theCourierGuy.southAfricaOnly === 'true') {
      var billingCountry = $('#billing_country')
      if (billingCountry.length > 0) {
        billingCountry.trigger('change')
      }
      var shippingCountry = $('#shipping_country')
      if (shippingCountry.length > 0) {
        shippingCountry.trigger('change')
      }
    }

    function triggerPlaceSelect(targetSelect, sourceSelect) {
      var placeLabelInput = sourceSelect.prev('input')
      var placeIdInput = placeLabelInput.prev('input')
      var newOption = new Option(placeLabelInput.val(), placeIdInput.val(), true, true)
      targetSelect.append(newOption).trigger('change')
      targetSelect.val(placeIdInput.val())
      targetSelect.trigger('change')
    }

    $('#ship-to-different-address-checkbox').on('change', function () {
      select2LocationSelect()
      var shipToDifferentAddressValue = $(this).prop('checked')
      var shippingPlaceSelect = $('#shipping_tcg_place_lookup')
      var billingPlaceSelect = $('#billing_tcg_place_lookup')
      if (shipToDifferentAddressValue === true) {
        $('#billing_insurance_field').hide()
        $('#billing_tcg_place_lookup_field').hide()
        triggerPlaceSelect(shippingPlaceSelect, billingPlaceSelect)
        if ($('#billing_insurance').prop('checked') === true) {
          $("#shipping_insurance").val(1);
          $("#shipping_insurance").prop('checked', true).attr('checked', 'checked').trigger('change')
        } else {
          $("#shipping_insurance").val(0);
          $("#shipping_insurance").prop('checked', false).removeAttr('checked').trigger('change')
        }
        $('p#billing_tcg_place_lookup_field').removeClass('validate-required')
        $('p#billing_tcg_place_lookup_field').removeClass('woocommerce-validated')
      } else {
        $('#billing_insurance_field').show()
        if ($('#billing_country').val() === 'ZA' || theCourierGuy.southAfricaOnly === 'false') {
          $('#billing_tcg_place_lookup_field').show()
        }
        triggerPlaceSelect(billingPlaceSelect, shippingPlaceSelect)
        if ($("#shipping_insurance").prop('checked') === true) {
          $('#billing_insurance').val(1);
          $('#billing_insurance').prop('checked', true).attr('checked', 'checked').trigger('change')
        } else {
          $('#billing_insurance').val(0);
          $('#billing_insurance').prop('checked', false).removeAttr('checked').trigger('change')
        }
        $('p#billing_tcg_place_lookup_field').removeClass('validate-required')
        $('p#billing_tcg_place_lookup_field').addClass('validate-required')
        $('p#billing_tcg_place_lookup_field').removeClass('woocommerce-validated')
        $('p#billing_tcg_place_lookup_field').addClass('woocommerce-validated')
      }
    })
    $('input[name="billing_tcg_place_lookup_place_label"]').on('change', function () {
      const lookupPlaceLabelSelector = $('input[name="billing_tcg_place_lookup_place_label"]')
      const proceedToPaymentButtonSelector = $('a:contains(\'Proceed to payment\')')
      if (lookupPlaceLabelSelector.val().trim() === '') {
        proceedToPaymentButtonSelector.hide()
      } else {
        proceedToPaymentButtonSelector.show()
      }
    })

    function hideProceedToPaymentOnLoad() {
      const lookupPlaceLabelSelector = $('input[name="billing_tcg_place_lookup_place_label"]')
      const proceedToPaymentButtonSelector = $('a:contains(\'Proceed to payment\')')
      if (lookupPlaceLabelSelector.length) {
        if (lookupPlaceLabelSelector.val().trim() === '' && proceedToPaymentButtonSelector.length) {
          proceedToPaymentButtonSelector.hide()
        }
      }
    }

    hideProceedToPaymentOnLoad()
    const suburbCityLabelSelectorBilling = $('label[for="billing_tcg_place_lookup"]')
    const suburbCityLabelSelectorShipping = $('label[for="shipping_tcg_place_lookup"]')
    const requiredAsterisk = '<abbr class="required" title="required">*</abbr>'

    function addAsteriskSuburbCity() {
      if (suburbCityLabelSelectorBilling.length && !suburbCityLabelSelectorBilling.html().includes(requiredAsterisk)) {
        suburbCityLabelSelectorBilling.append(requiredAsterisk)
      }
      if (suburbCityLabelSelectorShipping.length && !suburbCityLabelSelectorShipping.html().includes(requiredAsterisk)) {
        suburbCityLabelSelectorShipping.append(requiredAsterisk)
      }
    }

    addAsteriskSuburbCity()

    $('input#billing_first_name').on('change', function (ev) {
      $('body').trigger('update_checkout')
    })
    $('input#billing_last_name').on('change', function (ev) {
      $('body').trigger('update_checkout')
    })
    $('input#billing_phone').on('change', function (ev) {
      $('body').trigger('update_checkout')
    })
    $('input#billing_city').on('change', function (ev) {
      let method = false
      if (typeof iihtcgSelectors != 'undefined' && iihtcgSelectors) {
        $.each(iihtcgSelectors, function (i, selector) {
          if (selector.checked === true) {
            method = selector.value
          }
        })
      }
    })

    if (typeof $('#billing_insurance') != 'undefined') {
      setBillingInsuranceValues()
      $('#billing_insurance').val(0);
    }
    if (typeof $("#shipping_insurance") != 'undefined') {
      setShippingInsuranceValues()
      $('#shipping_insurance').val(0);
    }

    $(document).on('change', 'input[name*="tcg_ship_logic_"]', function (e) {
      const $optins = $('input[name*="tcg_ship_logic_"]')
      let optin = ''
      $.each($optins, (k, v) => {
        if (v.checked) {
          optin += v.value
        }
      })
      $('input#ship_logic_opt_ins').val(optin)
      $('input#ship_logic_opt_ins').trigger('change')
      $('body').trigger('update_checkout')
    })

    //Admin
    var suburbAdminSelect = $('select.tcg-suburb-field')
    if (suburbAdminSelect.length > 0) {
      suburbAdminSelect.select2({
        placeholder: 'Start typing Suburb name...',
        minimumInputLength: 3,
        ajax: {
          url: theCourierGuy.url,
          dataType: 'json',
          delay: 350,
          data: function (term) {
            return {
              q: term, // search term
              action: 'wc_tcg_get_places'
            }
          },
          closeOnSelect: true,
          processResults: function (data) {
            var results = []
            $.each(data, function (index, item) {
              results.push({
                id: item.suburb_key,
                text: item.suburb_value
              })
            })
            return {
              results: results,
            }
          },
          cache: false
        }
      }).on('change', function (evt) {
        $(this).children().each(function (k, v) {
          if (k == 1) {
            $('#woocommerce_the_courier_guy_shopPlace').val($(v).text())
          }
        })
      })
    }
    var overrideSelects = $('.tcg-override-per-service')
    var overrideInputs = $('.tcg-override-per-service-input')
    overrideSelects.on('change', function () {
      var selectedOptionValue = $(this).children('option:selected').val()
      if (selectedOptionValue !== '') {
        $(this).nextAll('span').hide()
        $(this).nextAll('span.tcg-override-per-service-span-' + selectedOptionValue).show()
      }
    })
    overrideInputs.on('blur', function () {
      var overrideSelect = $(this).parent('span').prevAll('select.tcg-override-per-service')
      var overrideValues = {}
      overrideSelect.nextAll('span').each(function () {
        var input = $(this).children('input')
        var serviceId = input.data('service-id')
        var overrideSelectOption = overrideSelect.find('option[value="' + serviceId + '"]')
        var serviceLabel = overrideSelectOption.data('service-label')
        var overrideValue = input.val()
        if (overrideValue !== '') {
          var prefix = ' - '
          if (input.hasClass('wc_input_price')) {
            prefix = ' - R '
            input.val(parseFloat(overrideValue).toFixed(2))
            overrideValue = input.val()
          }
          overrideValues[serviceId] = overrideValue
          serviceLabel = serviceLabel + prefix + overrideValue
        }
        overrideSelectOption.html(serviceLabel)
      })
      if (Object.keys(overrideValues).length > 0) {
        overrideSelect.nextAll('input').val(JSON.stringify(overrideValues))
      } else {
        overrideSelect.nextAll('input').val('')
      }
    })
  })
})
(jQuery)
