(function ($, Drupal) {
  Drupal.behaviors.qrCodeScanner = {
    attach: function (context, settings) {

      function removeFrontCameras() {
        const intervalId = setInterval(() => {
          const selectElement = document.getElementById('html5-qrcode-select-camera');
          if (selectElement) {
            document.querySelectorAll("#html5-qrcode-select-camera option").forEach(option => {
              if (option.textContent.toLowerCase().includes("front")) {
                option.remove();
              }
            });
            selectElement.selectedIndex = 0;
            clearInterval(intervalId);
          }
        }, 1000);
      }

      function onScanSuccess(decodedText, decodedResult) {
        var messageContainer = document.getElementById('validation-message');
        messageContainer.innerHTML = '<span><p class="scanner-txt">' + decodedText + '</p>';
        
        // Hide the scanner and show the "Start scanning" button
        document.getElementById('start-scanning').style.display = 'inline-block';

        html5QrcodeScanner.clear().catch(error => {
          console.error('Failed to clear the scanner:', error);
        });

      }

      // Function to start scanning.
      function startScanning() {
        document.getElementById('start-scanning').style.display = 'none';
        html5QrcodeScanner.render(onScanSuccess);
        removeFrontCameras();
      }

      var html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", { fps: 10, qrbox: 250 });
      html5QrcodeScanner.render(onScanSuccess);
      removeFrontCameras();

      // Attach the start scanning function to the button
      $('#start-scanning').on('click', startScanning);

    }
  };
})(jQuery, Drupal);
