{{-- Server-rendered essentials for the landing page: readable without JavaScript and by crawlers. --}}
<noscript>
    <h1>{{ $brand }}</h1>
    <p>{{ $description }}</p>
    <p>{{ $emailNote }}</p>
</noscript>
<div id="landing-essentials">
    <p>{{ $brand }}</p>
    <p>{{ $description }}</p>
    <p>{{ $emailNote }}</p>
</div>
<script>document.getElementById('landing-essentials').classList.add('sr-only');</script>
