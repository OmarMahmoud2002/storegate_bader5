// Switch links
$(".links_stng li").click(function () {
  $(this).addClass("active").siblings().removeClass("active");
  $(".all_sections > div").hide();
  $($(this).data("sec")).fadeIn();
});

// Add
$(".prod_info .all_sections .sec_she .inpt button").click(function(event){
    var shelf_number = $(event.target).parent().attr('id');
    $('#shelf_number_input').val(shelf_number)
    $('.popup-shelves-add .title h3 span').text(shelf_number);
    $(".popup-shelves-add").fadeIn(200);
});

// Add BARCODE
if ($('#barcodeRadio').is(':checked')) {
    $("#radioinput").removeClass('hide');
    $("#radioinput input").attr('disabled', false);
}

$('#barcodeRadio').click(function(){
    $("#radioinput").removeClass('hide');
    $("#radioinput input").attr('disabled', false);
});

$('#serialradio').click(function(){
    $('#radioinput').addClass('hide');
    $("#radioinput input").attr('disabled', true);
});

$('.edit-btn').click(function() {
    let serial_id = $(event.target).closest('.products-row').attr('id')
    $('.popup-poster-edit').fadeIn(200)
    $('#serial_id').val(serial_id)
})

$('.del-btn').click(function() {
    let serial_id = $(event.target).closest('.products-row').attr('id')
    $.post('./../delete_serial', {'id': serial_id}, () => {
        location.reload()
    })
})

// فلترة سيريالات المنتج حسب Subinventory Code مع تحديث العدد الظاهر.
const serialInventoryFilters = Array.from(document.querySelectorAll('.serial-inventory-filter'));
const serialInventoryItems = Array.from(document.querySelectorAll('.serials-grid .serial-item'));
const visibleSerialCount = document.getElementById('visibleSerialCount');
const serialFilterEmpty = document.getElementById('serialFilterEmpty');

serialInventoryFilters.forEach(function (filterButton) {
    filterButton.addEventListener('click', function () {
        const selectedCode = filterButton.dataset.serialFilter || 'all';
        let visibleCount = 0;

        serialInventoryFilters.forEach(function (button) {
            const isActive = button === filterButton;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        serialInventoryItems.forEach(function (serialItem) {
            const isVisible = selectedCode === 'all' || serialItem.dataset.subinventory === selectedCode;
            serialItem.classList.toggle('is-filtered-out', !isVisible);
            if (isVisible) {
                visibleCount++;
            }
        });

        if (visibleSerialCount) {
            visibleSerialCount.textContent = visibleCount;
        }
        if (serialFilterEmpty) {
            serialFilterEmpty.hidden = visibleCount !== 0;
        }
    });
});
