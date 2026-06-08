import React, { useEffect, useRef, useState } from 'react';
import getServers from '@/api/getServers';
import { getServerPlayers } from '@/api/server/players';

const NAMES = [
    '1krakensS','4rdenth','aaailyi','aaaqiff','AaronHakim','AbuDuh','abusyahril',
    'Adzimmm','Afiqgaming20','aimanhanif','aimanPOTTER','AinulHaziq_20','AiQuzo',
    'AiZKitsu','Akif_Iskandar','AkioRC','alifrider','Alistair833','allymoi',
    'AlterMasz','AmadKerut','Amarrrzz','Aminas','_Aminas_','AmmarZz','ANASHANIF',
    'anibisa47','AnLowky','apippo','Appleyy_','AquaRealMc','AraaBella','Arieff69',
    'ARIP_ONZ','ashemahh','AishFN','Asuiiz','asy_cherry','atokkk','Auliyaa',
    'ayahbotak','Ayie_','AZA0630','Azroy185','AzuiWho','azumaneee','B3ar1um_',
    'Babatlicious','BabyMyra4808','Bad137','Bal_oK','balqis0','barbarbv','Barbito_',
    'Bedulza','BewearTheWise','BlazingDude','Bluxberrygames','BobLing67','bttboolat',
    'Butterynn','c_ark','CainooX','CakeNICK','capikarae','ChiliaqApiaq','Chiriroo',
    'CikTira','Cloudzz08','Clover_Haruno','cocopieforlife','COKLATDARK7','Coklattz',
    'CUSH03','D4mzz','D4mzz_','DanishMayo','DannyDahsyat','dayahz06','DeanJenal',
    'deeeyyaa','DenJaehwan','DevilZi','DiBBy19','Dikmok','DiphenMy','DizBanton',
    'djkiller09','Domozim','DragonCHMC','DrFansSi2','DrFansSi4','DunaddShy','Duxxc',
    'eabidakk','Eff_88','einsalvinkomi','EligosVT','Elric','Ellynaloy','ElricMiura',
    'Epixla','Erthal_Arnold','EverestPolar','Exavya','ezforsaya','F1ezxNight',
    'FaaTotz','Faclon','Fadhli1305','Fahmiziiq','FaizFusserMC','Fauzan6061','FIQARSS',
    'FiyCrafted','Foogooo','formoonn','FoxySenpai03','Freddewafif','Friedens',
    'FrostHistory','Fur1n17','FyaIskandar','FyiSkyBin','gatzx','H4je','Hadif_okok',
    'Haevman','haih176','haikalazali','Hairoesninja','HakuVT','HakuVT22','hamada_0z',
    'HanzzMeow','HappySamaXD','HarizGaming','harukaaa_','Haseo27','Hazy_4',
    'HeroHunter48','HI_AAQILL','Hideyoshie','hrhrazek','hunter198465','IcyFab',
    'ijato_Ai','Ikeonys','ImSofea','InVdaPro','iqskndr','iquzo','iskand_2009',
    'itsFlurryy','Itskuze','ItsTooFarish','ItsyourNyx','IvaPava','JackLinYong',
    'jae_va','JaeminChan','JaiJaiAxolotl','Jarood','Jaszmineee','Javijavi_yay',
    'Jehangsenak','Jeniuscomel','JiKoshai','JinxTWT','Jojoh_12','jokoel','JordyB0i',
    'jude_boy','JuicyKay_','Kaelaaaa','KaiReiVT','KakOdette','Kaleees','Kamenzz',
    'kanna_tsu','kaptenn08','kaydenrk','KazenBoushi','Kei_Zam','KenzyGans',
    'KetupatHangus','Keymah_','Khafizie','KhongLzy','Kiaraaa','Kijiya_','kijiyaa',
    'Kito_Meow','KucingToman','KuramaAnak','Kyome','Lamboo04','Lano_chaN','Lavienx',
    'leezhan','LepKiut','LiilcJheng','LilMiss_','LilyssaLemon','LoneWolfx45',
    'LordJared_','LoyaltyIsH','Luna_Nota','Lyra_003','m_ahai','M4RI0_','Magixian',
    'mAkINiNe','MamatKosong','mango11s','Manisjeleke','Mar26_04','Marie_San07',
    'MARS_08','marshmello3105','mataa_air','Maticz','MaxyTheRex','MCB2023','meiynx',
    'MellysaMelly','MelloMsk','MHY_0802','Mhyst','mhysttt','Miera_Mc','MikaMarbless',
    'MIMIEM78','Mina_0403','Minatozaki_Sana','MineBryan','MiniHoop','Mir0ng',
    'Misha_Foxx','Miss_hm','Mitsuki_Code','MnM_811','MochaPocky','MochiMoonx',
    'Moepaii','MoonieBunnie','morriesan','mr_kambing69','MrPyro_99','mrsnow_22',
    'mumuhamad','My_My_','Mystogan1610','NaBeela17','Nabil_Aiman','Nadeem_Walker',
    'Nae_Na','Naeva','Nahdh','Najmi','najwanss','naramatcha','NattoDeer','Nazhan09',
    'Nazrin1207','Nazwan_08','NekoNeko_00','NeoBlaster','NeonNuggety','Nerdi3',
    'NerdyDK','NeyuNyu','Ni_Colai','NightCoreSlay','Nightmare_Ja','Nikki_2u',
    'NikkoRex07','NishaQistina','Nishiki_Mizu','NoNiQ_','Nono0807','Novaris_SMP',
    'Nqee','nurseila','Ny4a_','Ny4nC4t','Nymphaea_','ODW_XY','Oh_My_N3ko','OiFuu',
    'OniiChanBaka','Oreooos','Osha_','OsuNek0','Overhaul_My','OwO_Keiii','P4NKR4N0',
    'PadukaMC','Paiiiin','Palestine883','Pandora_Fz','Parfait_xD','pasukannn',
    'Pawernich','peachy_vq','Pearlie_0429','PeeKaa_','Phantom_Tactic','PhantomCherryy',
    'Phexinia','Piiinkz_','PinkiePumpkin','pipikane','pockyfox','Poggersss_',
    'PoisonIvy_','Ponponnnnnn','Poppyy_n','Prawnnnnn','Prescott','Princess_Aliya',
    'Puffyuu','PumpKin_e','Pure_Mist','PURPLECAT1410','Pwypy','Pyonnii','Qaiyum_',
    'Qhuey','Quacks_','QueenAmeera_','R0ssa','R3O__','Raeve_','RainbowPvP',
    'Raisyaaa_','Rannyo','RareCheese_','RavenAsh_26','Raymundo','Razman_','ReaIA',
    'ReiiJii','Reira_san','Relixor','Rem_ui','RequiemMM','Rexalico','Rezpect0',
    'RiiPaiiN','RikkaMawaruu','Rinnn_nya','Rinzler_606','Rippler_','rizqym','Rizzly_',
    'RizzPake','RocketFoxy','RokiLoves','RoronoaOda','RuangDz','rullyboy_','Rumi_',
    'Ruri_3113','Rynn_DS','ryu_0907','S4R4_28','Saaiiko','SacredVibes','Sai_Key',
    'Sakiina','Sakura_Alice','SamDawg_57','Samurai_jag','Sang_Kancil','Sanity_9',
    'Saphira_Eloria','Sashi_Desu','Saskia_0402','SavageWolf','Saya_Listy','Seal_',
    'SecretDs','Seeu_','SeijiVT','Seira_yaa','Senaaaaa','Serena_07_','Seroja',
    'Sh4dow_07','Sh4dyy_','Shandy_18','ShanQMeow','Shawlol_','Shawn_0825',
    'Shayulmaut','SheeshCraft','ShiEn027','ShiroUsagi_','ShiroVT','Shisuka',
    'Shoco_21','Shouko_uu','ShrimpCrackers','Shypiesss','SiewMay__10','SilentSlayer_',
    'silver_ax3l','Silversss__','Simp4Mei','Sindy_1610','Sir_Ariff','SiroCat',
    'Skylar_ProjectZ','SkySlash_','sleepy_maid','Slurp_07','SmilySyain','Snoozng',
    'Snowi_04','SodaBili','SonicQAQ','spacepandaa','SparkL_08','Splynter',
    'Spo0ky_Mul0','SpoOkiz','StarryNii','StarrySky_','StarryxPancake','Starwalk_',
    'Stern_','Strawb3rry_','Strawbearry','SugaMiko_','SukoiDesu','Summerz_29',
    'Sunaiya_A','Sushi_Fishy','SushiKiss','SuzumeVT','Syaaheed','Syahminnn',
    'Syahzee','Syaiful_Bruh','SyakirStrike','Syameow','SYAMII_','Syarif_f',
    'SyaStarry','Syazwan_txt','SyedDaniyal_','SYuhu_','T3mP3s','Tabyoka',
    'Taffy_owo','Tako_','Tamamo_','TanpaKoperek','tansui','Taruu','Taufiq0409',
    'TAYIQI','TayyLemon','TenshiD3su','TeraByte_','Terra_01_','TheBigPenguin',
    'TheeFlash_','TheNameHaidi','TheoWolf_','Thunderclaw','TickleMePink','Timah_Dua',
    'TitanSlayerMC','TmanGanz','TobeyTiger','TokayGecko_','Tol_','Tomoe_77',
    'TotallySpy','Tra_sHh_','Tsuki_','TukangBoba','TweetyBird_','Uchiha_Davva',
    'Udin_','ueuekue','UglyDuck_','Uhibuki','Umaru_Desu','UnicornEivor','UniquePotato',
    'Unkn0wn_xD','Uta_Desune','ValerieRenata','Valspar_','VanillaMilk','VanillaPocky',
    'vCrax','Velysan','Venom_Stone','Venomous_','Venti_','Vespera_','VicKage_',
    'Vict0ry_','Violet_','Violeta_','Vixxen_','VoidLurker','Vynl_','Wafiy_',
    'Wafiyy_','WaterMe10n_','Wawarisdead','weird_flex','WhiteLotus_','Windy_',
    'Wolfer_','Wolfiey_','Wuland_','Wynona_','X3n0n_','Xana_','Xarvis_','Xaviera_',
    'Xenovia_','Xiao_','Xilonen_','Xion_','Xius_','Xylox_','Yamato_','Yandere_',
    'Yasha_','Yato_','Yatsu_','Yelan_','Yennefer_','Yoko_','Yor_','Yoru_',
    'Yoshino_','Yuki_','Yukine_','Yukino_','Yumeko_','Yumina_','Yuna_','Yuni_',
    'Yuno_','Yuuki_','Yuuna_','Yuzu_','Zack_','Zafran_','Zahra_','Zai_','Zaki_',
    'Zara_','Zayn_','Zaza_','Zelda_','Zeo_','Zero_','Zetta_','Zia_','Zion_','Zira_',
    'Zoe_','Zola_','Zora_','Zuko_','Zula_','Zuri_','Ayienadzri21','kucennn','Qih__',
    'Nahoplays','EzplayzG','SofeaHikari','JenglotMerah','itsFlurry','Shahqeer2003',
    'Mal_Morset','iwasumighost','OmenDarkonov_','Yasz03','ThalhahHaCh','braxten_yuvi',
    'NotssNad1m','rabinoha','Mashi03','aaishyy','Mikooooo__','l9na','NaqaL1',
    'azimite','Shintaro05','ShinTsurugi24','Aekryz','AEPPCo','Susu_Pahit','Itsizani',
    'Husavarman','Harfi718','Lucidmy','ItzMizie','Kayynis','DatoMeaw','Michiruu07',
    'bobabrianaa','hansthepros','ClayMan73','PooferFishy','AshotSenpai','YuuyaAminn',
    'LixeKaisa','Shadow_MD','semutkhayal','M4L0KA','Fai_Heart','Mimichan28',
    'kataqkun','MrZemini','Takeyuki123','KnightHMY','AbuJebat','SylasKaelvar',
    'AisyAeeee','TokyotoRe','Virusmang','BlazeR_610','Ploofnix','ZoelCH','FarisFs',
    'OliverrAkiyonori','Danriqq','CORRUPTWIZARD','kito_5hp','_Rentaka','Lemoncutie1',
    'Li4lithium','Kaizersz77','LunaMisaki','DarkAmethyst505','Oosaragii','itzAHrizz',
    'NisOtaku03',
];

interface BackgroundPlayer {
    uuid: string;
    name: string;
    isOnline: boolean;
}

// Fetch players from all Minecraft servers on the panel that support the players API.
// Deduplicates by UUID; online status takes priority if a player appears in multiple servers.
// Polls every 45 s so online/offline state stays fresh.
interface UseBackgroundPlayersResult {
    players: BackgroundPlayer[];
    loaded: boolean;
}

function useBackgroundPlayers(): UseBackgroundPlayersResult {
    const [players, setPlayers] = useState<BackgroundPlayer[]>([]);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        let cancelled = false;

        async function fetchAll() {
            try {
                // Collect all server UUIDs (paginate up to 5 pages)
                const serverUuids: string[] = [];
                for (let page = 1; page <= 5; page++) {
                    const result = await getServers({ page });
                    serverUuids.push(...result.items.map((s) => s.uuid));
                    if (result.pagination.currentPage >= result.pagination.totalPages) break;
                }

                // Query each server for players; ignore servers that fail or return dummy data
                const playerMap = new Map<string, BackgroundPlayer>();
                await Promise.allSettled(
                    serverUuids.map(async (uuid) => {
                        try {
                            const res = await getServerPlayers(uuid, { scope: 'all' });
                            // is_dummy === true means the server has no real player-tracking integration
                            if (res.is_dummy) return;
                            for (const p of res.items) {
                                if (!p.uuid || !p.name) continue;
                                const isOnline = p.status === 'online';
                                const existing = playerMap.get(p.uuid);
                                // Keep entry if new, or upgrade to online if already stored as offline
                                if (!existing || isOnline) {
                                    playerMap.set(p.uuid, { uuid: p.uuid, name: p.name, isOnline });
                                }
                            }
                        } catch {
                            // Server does not support the players API — skip silently
                        }
                    })
                );

                if (cancelled) return;
                setPlayers(Array.from(playerMap.values()));
            } catch {
                // Network error or panel offline — keep current state
            } finally {
                if (!cancelled) setLoaded(true);
            }
        }

        fetchAll();
        const id = setInterval(fetchAll, 45_000);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, []);

    return { players, loaded };
}

// Golden angle in radians — drives the phyllotaxis spiral
const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));
const ACTIVE_BLINKS = 90;

const KEYFRAMES = `
@keyframes cb-breathe {
    0%   { filter: grayscale(100%) contrast(1.2) brightness(0.95); transform: scale(1); }
    35%  { filter: grayscale(0%) contrast(1) brightness(1.15);     transform: scale(1.1); }
    50%  { filter: grayscale(0%) contrast(1) brightness(1.15);     transform: scale(1.1); }
    65%  { filter: grayscale(0%) contrast(1) brightness(1.15);     transform: scale(1.1); }
    100% { filter: grayscale(100%) contrast(1.2) brightness(0.95); transform: scale(1); }
}
@keyframes cb-pulse-online {
    0%   { filter: grayscale(0%) contrast(1) brightness(1.1); transform: scale(1); }
    50%  { filter: grayscale(0%) contrast(1) brightness(1.4); transform: scale(1.12); }
    100% { filter: grayscale(0%) contrast(1) brightness(1.1); transform: scale(1); }
}
`;

interface Cell {
    el: HTMLDivElement;
    isOnline: boolean;
}

export default function ConsoleBackground() {
    const { players, loaded } = useBackgroundPlayers();
    const containerRef = useRef<HTMLDivElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const gridRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = containerRef.current;
        const canvas = canvasRef.current;
        const grid = gridRef.current;
        if (!container || !canvas || !grid) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        // Build cells from live player data, or fall back to hardcoded names
        const cells: Cell[] = [];

        const buildCell = (src: string, alt: string, isOnline: boolean) => {
            const cell = document.createElement('div');
            cell.style.cssText = 'position:absolute;transform:translate(-50%,-50%)';
            const img = document.createElement('img');
            img.src = `https://mc-heads.net/body/${src}/80`;
            img.alt = alt;
            img.loading = 'lazy';
            const defaultFilter = isOnline
                ? 'grayscale(0%) contrast(1) brightness(1.1)'
                : 'grayscale(100%) contrast(1.2) brightness(0.95)';
            img.style.cssText = `display:block;image-rendering:pixelated;filter:${defaultFilter};height:5.5vh;width:auto;`;
            img.onerror = () => { img.style.display = 'none'; };
            cell.appendChild(img);
            grid.appendChild(cell);
            cells.push({ el: cell, isOnline });
        };

        if (players.length > 0) {
            // Real player data: shuffle + double so we fill the spiral nicely
            const base = [...players].sort(() => Math.random() - 0.5);
            const doubled = [...base, ...base.slice().sort(() => Math.random() - 0.5)];
            for (const p of doubled) buildCell(p.uuid, p.name, p.isOnline);
        }
        // loaded=false OR loaded=true with no players → empty grid, particles only

        // Phyllotaxis spiral layout — matches the original HTML algorithm
        function layout() {
            const vw = container!.clientWidth;
            const vh = container!.clientHeight;
            if (!vw || !vh) return;
            const aspect = vw / vh;
            const n = cells.length;
            const rMin = 0.22;
            const rMin2 = rMin * rMin;

            for (let i = 0; i < n; i++) {
                const frac = Math.sqrt(rMin2 + (1 - rMin2) * (i + 0.5) / n);
                const theta = i * GOLDEN_ANGLE;
                const cosT = Math.cos(theta);
                const sinT = Math.sin(theta);

                let stretch = 1;
                if (frac > 0.5) {
                    const t = (frac - 0.5) / 0.5;
                    const corner = 1 / Math.max(Math.abs(cosT), Math.abs(sinT));
                    stretch = 1 + (corner - 1) * t * t;
                }

                const rx = frac * stretch * cosT;
                const ry = frac * stretch * sinT;
                const x = 50 + rx * 52 * aspect * 0.58;
                const y = 50 + ry * 54;

                cells[i].el.style.left = `${x}%`;
                cells[i].el.style.top = `${y}%`;
                cells[i].el.style.zIndex = String(Math.round(y * 10));
            }
        }

        layout();

        // Upward-drifting amber particles
        function resizeCanvas() {
            canvas!.width = container!.clientWidth;
            canvas!.height = container!.clientHeight;
        }
        resizeCanvas();

        interface Particle { x: number; y: number; vx: number; vy: number; r: number; a: number; }
        const particles: Particle[] = Array.from({ length: 40 }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            vx: (Math.random() - 0.5) * 0.15,
            vy: -0.08 - Math.random() * 0.12,
            r: 0.5 + Math.random() * 1.5,
            a: 0.08 + Math.random() * 0.15,
        }));

        let animId: number;
        function draw() {
            ctx!.clearRect(0, 0, canvas!.width, canvas!.height);
            for (const p of particles) {
                p.x += p.vx;
                p.y += p.vy;
                if (p.y < -10) { p.y = canvas!.height + 10; p.x = Math.random() * canvas!.width; }
                if (p.x < -10) p.x = canvas!.width + 10;
                if (p.x > canvas!.width + 10) p.x = -10;
                ctx!.beginPath();
                ctx!.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx!.fillStyle = `rgba(196,164,74,${p.a})`;
                ctx!.fill();
            }
            animId = requestAnimationFrame(draw);
        }
        draw();

        const ro = new ResizeObserver(() => {
            layout();
            resizeCanvas();
        });
        ro.observe(container);

        // Cycling blink animations: 90 running concurrently, each restarts after finishing.
        // Online players pulse with color; offline players blink from grayscale to color and back.
        const blinking = new Set<number>();
        const timeoutIds: ReturnType<typeof setTimeout>[] = [];
        let alive = true;

        function startBlink() {
            if (!alive) return;
            let tries = 0, idx = 0;
            do {
                idx = Math.floor(Math.random() * cells.length);
                tries++;
            } while (blinking.has(idx) && tries < 20);
            if (blinking.has(idx)) return;
            blinking.add(idx);

            const cell = cells[idx];
            const img = cell?.el.querySelector('img') as HTMLImageElement | null;
            if (!img) { blinking.delete(idx); return; }

            const dur = 1.8 + Math.random() * 1.2;
            // Online: subtle brightness pulse (stays colored); Offline: grayscale → colored → grayscale
            img.style.animation = cell.isOnline
                ? `cb-pulse-online ${dur}s ease-in-out`
                : `cb-breathe ${dur}s ease-in-out`;

            const done = () => {
                img.style.animation = '';
                img.removeEventListener('animationend', done);
                blinking.delete(idx);
                if (alive) {
                    const t = setTimeout(startBlink, 50 + Math.random() * 200);
                    timeoutIds.push(t);
                }
            };
            img.addEventListener('animationend', done);
        }

        const initT = setTimeout(() => {
            for (let k = 0; k < ACTIVE_BLINKS; k++) {
                const t = setTimeout(startBlink, k * 120);
                timeoutIds.push(t);
            }
        }, 300);
        timeoutIds.push(initT);

        return () => {
            alive = false;
            cancelAnimationFrame(animId);
            ro.disconnect();
            timeoutIds.forEach(clearTimeout);
            while (grid.firstChild) grid.removeChild(grid.firstChild);
        };
    }, [players, loaded]); // Rebuild grid when player data arrives or online status changes

    return (
        <div
            ref={containerRef}
            style={{ position: 'absolute', inset: 0, overflow: 'hidden', background: '#151510', zIndex: 0 }}
        >
            <style>{KEYFRAMES}</style>

            {/* Upward-drifting amber particles */}
            <canvas
                ref={canvasRef}
                style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', zIndex: 1, pointerEvents: 'none' }}
            />

            {/* Player body spiral grid — populated imperatively in useEffect */}
            <div
                ref={gridRef}
                style={{ position: 'absolute', inset: 0, zIndex: 2 }}
            />

            {/* Dark vignette — keeps terminal text readable */}
            <div
                style={{
                    position: 'absolute',
                    inset: 0,
                    zIndex: 3,
                    background: 'radial-gradient(ellipse at center, rgba(21,21,16,0.55) 0%, rgba(21,21,16,0.82) 100%)',
                    pointerEvents: 'none',
                }}
            />
        </div>
    );
}
