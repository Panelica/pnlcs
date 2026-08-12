<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName ?? 'PNLCS' }} — {{ __('client.welcome.meta_title_suffix') }}</title>
    <meta name="description" content="{{ __('client.welcome.meta_description', ['brand' => $brandName ?? 'PNLCS']) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
    <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    @include('sections.styles')
    @if(!empty($activeThemeAssets))
    <link rel="stylesheet" href="{{ $activeThemeAssets }}/css/theme.css">
    @endif
</head>
<body x-data="{ mobileMenu: false }">

    {{-- Fixed sections: topbar + navigation --}}
    @include('sections.topbar')
    @include('sections.navigation')

    {{-- Dynamic sections from DB --}}
    @foreach($sections as $section)
        @if($section->is_enabled && $section->slug !== 'footer' && view()->exists("sections.{$section->slug}"))
            @include("sections.{$section->slug}", [
                'section' => $section,
                'content' => $sectionContent[$section->slug] ?? collect(),
                'products' => $products ?? collect(),
                'domainPricing' => $domainPricing ?? collect(),
            ])
        @endif
    @endforeach

    {{-- Fixed section: footer --}}
    @include('sections.footer', [
        'content' => $sectionContent['footer'] ?? collect(),
    ])

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Three.js Globe Animation --}}
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
    <script>
    (function() {
        const canvas = document.getElementById('heroCanvas');
        if (!canvas) return;

        const cs = getComputedStyle(document.documentElement);
        function themeColor(name, fallback) {
            const v = cs.getPropertyValue('--theme-' + name).trim();
            return v || fallback;
        }
        const colPrimary = new THREE.Color(themeColor('welcome-primary', '#2563eb'));
        const colAccent = new THREE.Color(themeColor('welcome-accent', '#10b981'));
        const colSecondary = new THREE.Color(themeColor('welcome-secondary', '#7c3aed'));

        const W = canvas.clientWidth, H = canvas.clientHeight;
        const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
        renderer.setSize(W, H);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, W / H, 0.1, 100);
        camera.position.z = 4.5;

        const globeGeo = new THREE.IcosahedronGeometry(1.5, 3);
        const globeMat = new THREE.MeshBasicMaterial({ color: colPrimary, wireframe: true, transparent: true, opacity: 0.18 });
        const globe = new THREE.Mesh(globeGeo, globeMat);
        scene.add(globe);

        const glowGeo = new THREE.SphereGeometry(1.48, 32, 32);
        const glowMat = new THREE.MeshBasicMaterial({ color: colPrimary, transparent: true, opacity: 0.04 });
        scene.add(new THREE.Mesh(glowGeo, glowMat));

        const dotCount = 120;
        const dotGeo = new THREE.BufferGeometry();
        const dotPos = new Float32Array(dotCount * 3);
        const dotCol = new Float32Array(dotCount * 3);
        for (let i = 0; i < dotCount; i++) {
            const phi = Math.acos(2 * Math.random() - 1);
            const theta = Math.random() * Math.PI * 2;
            const r = 1.52;
            dotPos[i*3] = r * Math.sin(phi) * Math.cos(theta);
            dotPos[i*3+1] = r * Math.sin(phi) * Math.sin(theta);
            dotPos[i*3+2] = r * Math.cos(phi);
            const c = [colAccent, colPrimary, colSecondary][i % 3];
            dotCol[i*3] = c.r; dotCol[i*3+1] = c.g; dotCol[i*3+2] = c.b;
        }
        dotGeo.setAttribute('position', new THREE.BufferAttribute(dotPos, 3));
        dotGeo.setAttribute('color', new THREE.BufferAttribute(dotCol, 3));
        const dotMat = new THREE.PointsMaterial({ size: 0.04, vertexColors: true, transparent: true, opacity: 0.85 });
        const dots = new THREE.Points(dotGeo, dotMat);
        scene.add(dots);

        function makeRing(radius, color, tilt) {
            const ringGeo = new THREE.RingGeometry(radius - 0.005, radius + 0.005, 128);
            const ringMat = new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.2, side: THREE.DoubleSide });
            const ring = new THREE.Mesh(ringGeo, ringMat);
            ring.rotation.x = tilt;
            return ring;
        }
        const ring1 = makeRing(2.0, colAccent, 1.2);
        const ring2 = makeRing(2.3, colSecondary, 0.6);
        scene.add(ring1, ring2);

        const satGroup = new THREE.Group();
        const satCount = 6;
        const sats = [];
        for (let i = 0; i < satCount; i++) {
            const sg = new THREE.SphereGeometry(0.035, 8, 8);
            const sm = new THREE.MeshBasicMaterial({ color: i % 2 === 0 ? colAccent : colSecondary });
            const sat = new THREE.Mesh(sg, sm);
            sat.userData = { radius: 1.9 + Math.random() * 0.5, speed: 0.3 + Math.random() * 0.4, offset: (i / satCount) * Math.PI * 2, tilt: 0.5 + Math.random() * 1.2 };
            satGroup.add(sat);
            sats.push(sat);
        }
        scene.add(satGroup);

        const lineCount = 20;
        const lineMat = new THREE.LineBasicMaterial({ color: colAccent, transparent: true, opacity: 0.12 });
        const lineGroup = new THREE.Group();
        for (let i = 0; i < lineCount; i++) {
            const a = Math.floor(Math.random() * dotCount);
            const b = Math.floor(Math.random() * dotCount);
            const lg = new THREE.BufferGeometry().setFromPoints([
                new THREE.Vector3(dotPos[a*3], dotPos[a*3+1], dotPos[a*3+2]),
                new THREE.Vector3(dotPos[b*3], dotPos[b*3+1], dotPos[b*3+2])
            ]);
            lineGroup.add(new THREE.Line(lg, lineMat));
        }
        scene.add(lineGroup);

        let mouseX = 0, mouseY = 0;
        document.addEventListener('mousemove', function(e) {
            mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
            mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
        });

        let time = 0;
        function animate() {
            requestAnimationFrame(animate);
            time += 0.005;
            globe.rotation.y = time * 0.3 + mouseX * 0.15;
            globe.rotation.x = Math.sin(time * 0.2) * 0.1 + mouseY * 0.1;
            dots.rotation.y = globe.rotation.y;
            dots.rotation.x = globe.rotation.x;
            lineGroup.rotation.y = globe.rotation.y;
            lineGroup.rotation.x = globe.rotation.x;
            ring1.rotation.z = time * 0.15;
            ring2.rotation.z = -time * 0.1;
            for (const sat of sats) {
                const d = sat.userData;
                const t = time * d.speed + d.offset;
                sat.position.x = d.radius * Math.cos(t) * Math.cos(d.tilt * 0.3);
                sat.position.y = d.radius * Math.sin(t) * Math.sin(d.tilt);
                sat.position.z = d.radius * Math.sin(t) * Math.cos(d.tilt);
            }
            lineMat.opacity = 0.08 + Math.sin(time * 3) * 0.06;
            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', function() {
            const w = canvas.clientWidth, h = canvas.clientHeight;
            if (w && h) {
                renderer.setSize(w, h);
                camera.aspect = w / h;
                camera.updateProjectionMatrix();
            }
        });
    })();
    </script>
    {{-- Dark mode toggle JS --}}
    <script>
    function toggleDarkMode() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var newMode = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newMode);
        document.cookie = 'pnlcs_theme=' + newMode + ';path=/;max-age=31536000;SameSite=Lax';
        var li = document.getElementById('lightIcon');
        var di = document.getElementById('darkIcon');
        if (li && di) {
            li.style.display = isDark ? '' : 'none';
            di.style.display = isDark ? 'none' : '';
        }
    }
    (function() {
        var m = document.cookie.match(/pnlcs_theme=(\w+)/);
        if (m && m[1] === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            var li = document.getElementById('lightIcon');
            var di = document.getElementById('darkIcon');
            if (li) li.style.display = '';
            if (di) di.style.display = 'none';
        }
    })();
    </script>
</body>
</html>
