<?php
$activePage = 'profile';
$pageTitle = "Company Profile | Jero &amp; Vonn's Six Star Hotel";
$htmlClass = 'dark';
$bodyClass = 'bg-background text-on-background font-body selection:bg-primary selection:text-background';
$headExtras = <<<'HTML'
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&amp;family=Cinzel+Decorative:wght@400;700;900&amp;family=Crimson+Pro:ital,wght@0,400;0,600;1,400&amp;family=Public+Sans:wght@300;400;600&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          "secondary-fixed-dim": "#d1c5b2",
          "outline-variant": "#504536",
          "on-tertiary-fixed-variant": "#5e4100",
          "primary-container": "#c8922a",
          "on-surface-variant": "#d4c4b0",
          "error-container": "#93000a",
          "surface-variant": "#353438",
          "surface-container-low": "#1b1b1e",
          "tertiary": "#f7bd4d",
          "on-tertiary-fixed": "#271900",
          "tertiary-fixed-dim": "#f7bd4d",
          "on-secondary-fixed-variant": "#4e4637",
          "secondary-container": "#4e4637",
          "error": "#ffb4ab",
          "surface-container": "#1f1f22",
          "on-primary": "#422c00",
          "on-error-container": "#ffdad6",
          "background": "#0d0d0f",
          "outline": "#9c8f7c",
          "surface-container-lowest": "#0e0e11",
          "surface-container-highest": "#353438",
          "primary": "#c8922a",
          "primary-fixed-dim": "#f8bc51",
          "on-secondary-container": "#c0b3a1",
          "on-primary-container": "#462f00",
          "on-surface": "#e8e0d0",
          "surface-dim": "#131316",
          "on-background": "#e8e0d0",
          "surface": "#151518",
          "secondary": "#d1c5b2",
          "on-primary-fixed": "#281900",
          "secondary-fixed": "#eee1cd",
          "surface-tint": "#c8922a",
          "surface-bright": "#39393c",
          "surface-container-high": "#2a2a2d",
          "primary-fixed": "#ffdeab",
          "on-tertiary": "#422c00",
          "inverse-on-surface": "#303033",
          "on-secondary-fixed": "#211b0f",
          "tertiary-fixed": "#ffdea9",
          "inverse-primary": "#7e5700",
          "on-secondary": "#372f22",
          "on-primary-fixed-variant": "#5f4100",
          "on-error": "#690005",
          "inverse-surface": "#e4e1e6",
          "tertiary-container": "#c79224",
          "on-tertiary-container": "#452f00"
        },
        fontFamily: {
          "headline": ["Cinzel Decorative", "serif"],
          "body": ["Crimson Pro", "serif"],
          "label": ["Cinzel", "serif"]
        },
        borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"},
      },
    },
  }
</script>
HTML;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="pt-20">
	<section class="relative h-[409px] flex items-center justify-center overflow-hidden">
		<div class="absolute inset-0 z-0 bg-cover bg-center opacity-40 grayscale" data-alt="atmospheric dark grey stone castle wall texture with dramatic shadows and medieval architectural details" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBjLbHujLUVXA_jSC03QbnULf1ckpG_xgMzemywvKM2njq-fUcJ6tQtL7t12EizMKG8bl1GV8pFR8hhKouiJT3Rax_WkJnHlNNazwYeY9U2x7wDgYolCZWSseOnuz4Ji6DMzAzZvmL969RqtYlqrYU4PZBCe8mFnQx3FThBo7eWNDSEul-YSVMLwwLt5OWI8k-BrUL_3Grb_zdz5n-PVcGIi-IMdEVue581Gvn3b6wxMR5VSwjZoAFlLDKBtGeOF-tcGvljlbUzA9w')"></div>
		<div class="absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent z-10"></div>
		<div class="absolute inset-0 bg-black/40 z-0"></div>
		<div class="relative z-20 text-center px-4" style="animation: riseIn 0.8s ease;">
			<h1 class="font-headline text-4xl md:text-6xl tracking-[0.25em] text-primary mb-4">THE ROYAL HOUSE</h1>
			<p class="font-body italic text-xl md:text-2xl text-secondary-fixed-dim">Our Legacy, Our Honour</p>
		</div>
	</section>

	<section class="py-24 bg-surface px-6 md:px-12">
		<div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
			<div class="relative group">
				<div class="absolute inset-0 border border-primary/20 translate-x-4 translate-y-4 group-hover:translate-x-2 group-hover:translate-y-2 transition-transform duration-500"></div>
				<div class="relative aspect-square bg-[#0d0d0f] border border-primary/30 overflow-hidden flex items-center justify-center p-12">
					<img alt="Heraldic crest" class="w-full h-full object-contain opacity-80" data-alt="ancient silver and gold royal coat of arms crest featuring two lions rampant on a dark ornate shield" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBkDDxvrNxH1IT-7Y1WB3TVkBomp3BJImujvjD2qeeKIL7oe10sPUDkSsG9box25oGD7hqOR52mcrg4zfIZ5_WpEdzZfYd_grwIUz8aIAAYhHwmw3LfegPv5oXtSUETyvvg-bZxLk6XsdW9SmZ8gomlQX2LjSMfTk6obiSLoqMlFqDYthBG_S-HTSL9c2fg-jVcqQ-G8mzIpnNQGii3-e_t__nv9pmAPk0nZCkLonSYhc-bxQYisL-AYDHSNZCN2aQxWG9PwdloRX4"/>
				</div>
			</div>
			<div class="space-y-6 text-lg leading-relaxed text-on-surface-variant">
				<h2 class="font-headline text-2xl tracking-widest text-primary mb-8">OUR ORIGINS</h2>
				<p class="dropcap">Founded in the heart of metropolitan Manila, Jero &amp; Vonn's Royal Chambers began as a vision to marry contemporary luxury with the timeless grandeur of medieval regality. What started as a singular passion for high-end hospitality has flourished into an establishment renowned for its unwavering commitment to the noble art of service.</p>
				<p>Each stone and tapestry within our halls tells a story of meticulous curation. We believe that true luxury is found in the silence of excellence and the precision of detail. Our lineage is defined not just by the bricks we lay, but by the memories we forge for our esteemed guests who seek refuge from the mundane.</p>
				<p>Today, we stand as a beacon of sophistication, offering a sanctuary where every visitor is treated with the reverence of royalty. Our honour is rooted in your comfort, and our legacy is written with every key turned and every dream realized within these sacred walls.</p>
			</div>
		</div>
	</section>

	<section class="py-24 px-6 md:px-12 bg-background relative overflow-hidden">
		<div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8">
			<div class="bg-[#1e1e24] p-12 border border-primary/20 hover:border-primary/40 transition-all duration-500 shadow-xl group">
				<div class="mb-6">
					<span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings: 'FILL' 1;">shield</span>
				</div>
				<h3 class="font-headline text-xl tracking-widest text-primary mb-6">OUR MISSION</h3>
				<p class="text-on-surface-variant text-lg leading-relaxed font-body">
					To provide an unparalleled sanctuary of regality where every guest experiences the pinnacle of personalized service. We strive to preserve the traditions of hospitality while integrating modern comforts, ensuring that the spirit of honour and excellence permeates every interaction.
				</p>
			</div>
			<div class="bg-[#1e1e24] p-12 border border-primary/20 hover:border-primary/40 transition-all duration-500 shadow-xl group">
				<div class="mb-6">
					<span class="material-symbols-outlined text-primary text-4xl" style="font-variation-settings: 'FILL' 1;">visibility</span>
				</div>
				<h3 class="font-headline text-xl tracking-widest text-primary mb-6">OUR VISION</h3>
				<p class="text-on-surface-variant text-lg leading-relaxed font-body">
					To be recognized globally as the premier destination for cinematic medieval hospitality. We envision a world where the elegance of the past and the innovation of the future coexist perfectly, setting the gold standard for luxury lodging in the Philippine archipelago and beyond.
				</p>
			</div>
		</div>
	</section>

	<section class="py-24 px-6 md:px-12 bg-surface">
		<div class="max-w-7xl mx-auto">
			<div class="bg-[#1b1b1e] border border-primary/20 p-12 md:p-16 shadow-2xl relative">
				<div class="grid grid-cols-2 lg:grid-cols-4 gap-12 text-center">
					<div class="flex flex-col items-center">
						<span class="font-headline text-3xl text-primary mb-2">Est. 2006</span>
						<span class="font-label text-xs uppercase tracking-widest text-outline">Founding Year</span>
					</div>
					<div class="flex flex-col items-center">
						<span class="font-headline text-3xl text-primary mb-2">3 Chamber Types</span>
						<span class="font-label text-xs uppercase tracking-widest text-outline">Themed Suites</span>
					</div>
					<div class="flex flex-col items-center">
						<span class="font-headline text-3xl text-primary mb-2">9 Room Configurations</span>
						<span class="font-label text-xs uppercase tracking-widest text-outline">Bespoke Layouts</span>
					</div>
					<div class="flex flex-col items-center">
						<span class="font-headline text-3xl text-primary mb-2">Manila, Philippines</span>
						<span class="font-label text-xs uppercase tracking-widest text-outline">Primary Realm</span>
					</div>
				</div>
			</div>
		</div>
	</section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
