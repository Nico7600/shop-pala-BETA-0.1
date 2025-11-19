<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<footer class="bg-gradient-to-r from-gray-800 via-gray-900 to-gray-800 border-t-2 border-purple-500/30 mt-8 sm:mt-16 py-2 sm:py-4">
    <div class="container mx-auto px-2 sm:px-4">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4">
            <!-- Logo et nom -->
            <div class="flex items-center gap-2">
                <i class="fas fa-gem text-purple-400 text-lg sm:text-xl"></i>
                <span class="text-lg sm:text-xl font-bold bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
                    CrazySouls Shop
                </span>
            </div>
            
            <p class="text-gray-400 text-center text-xs sm:text-base">
                &copy; <?php echo date('Y'); ?> - Tous droits réservés
            </p>
            
            <!-- Lien dinosaure -->
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="#" id="dino-link" class="text-gray-400 hover:text-purple-400 transition-colors">
                    <i class="fa-solid fa-cat text-3xl sm:text-5xl"></i>
                </a>
            </div>
        </div>
    </div>
</footer>
<script>
document.getElementById('dino-link').addEventListener('click', function(e) {
    e.preventDefault();
    if (confirm('Voulez-vous voir un DaInOsOr ?')) {
        window.open('https://www.tiktok.com/@tabbykatbros/video/7566399862871297293?is_from_webapp=1&sender_device=pc&web_id=7544508411423442465', '_blank');
    }
});
</script>
