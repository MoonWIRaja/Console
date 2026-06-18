import React, { useEffect, useRef, useState } from 'react';
import http from '@/api/http';

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

// Pulls the roster from one cached, server-side endpoint: unique de-duplicated premium
// players (online-mode=true only), each flagged with whether they are currently online.
// Polled every minute so the online glow stays live.
interface UseBackgroundPlayersResult {
    players: BackgroundPlayer[];
    online: Set<string>;
}

// Hardcoded names are kept only as a last-resort fallback so the spiral is never
// completely empty if the roster endpoint is briefly unreachable.
const FALLBACK_PLAYERS: BackgroundPlayer[] = NAMES.map((name) => ({ uuid: name, name, isOnline: false }));

function useBackgroundPlayers(): UseBackgroundPlayersResult {
    const [players, setPlayers] = useState<BackgroundPlayer[]>([]);
    const [online, setOnline] = useState<Set<string>>(() => new Set());
    // Signature of the current roster membership; the grid is only rebuilt when this
    // changes, so frequent online-status polls never churn the (large) spiral.
    const membershipRef = useRef<string>('');

    useEffect(() => {
        let cancelled = false;

        async function fetchRoster() {
            try {
                const { data } = await http.get('/api/client/minecraft-roster');
                const list: Array<{ name: string; uuid: string; online?: boolean }> = data?.data ?? [];
                if (cancelled) return;

                const valid = list.filter((p) => p && p.name);
                const source: BackgroundPlayer[] =
                    valid.length > 0
                        ? valid.map((p) => ({ uuid: p.uuid || p.name, name: p.name, isOnline: false }))
                        : FALLBACK_PLAYERS;

                const signature = source.map((p) => p.name).join('\n');
                if (signature !== membershipRef.current) {
                    membershipRef.current = signature;
                    setPlayers(source);
                }

                setOnline(new Set(valid.filter((p) => p.online).map((p) => p.name.toLowerCase())));
            } catch {
                if (!cancelled && membershipRef.current === '') {
                    membershipRef.current = FALLBACK_PLAYERS.map((p) => p.name).join('\n');
                    setPlayers(FALLBACK_PLAYERS);
                }
            }
        }

        fetchRoster();
        const id = setInterval(fetchRoster, 60_000); // poll every minute for online updates
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, []);

    return { players, online };
}

// Golden angle in radians — drives the phyllotaxis spiral
const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));
const KEYFRAMES = `
/* Online players slowly breathe from dark (like offline) to full colour and back. */
@keyframes cb-online-breathe {
    0%, 100% { filter: grayscale(100%) contrast(0.95) brightness(0.05); }
    50%      { filter: grayscale(0%) contrast(1.06) brightness(1.18); }
}
/* Crisp drop-shadow so console log text stays legible over the player wallpaper. */
.xterm-rows span { text-shadow: 0 1px 2px rgba(0, 0, 0, 0.95), 0 0 3px rgba(0, 0, 0, 0.8); }
`;

// Offline players sit static in the dark; online players slowly breathe to full colour.
const OFFLINE_FILTER = 'grayscale(100%) contrast(0.95) brightness(0.05)';
const ONLINE_FILTER = 'grayscale(0%) contrast(1.06) brightness(1.18)';

// Online = slow colour breathe (dark → full colour → dark); offline = static & dark.
function applyCellState(img: HTMLImageElement, isOnline: boolean): void {
    if (isOnline) {
        const dur = 4 + Math.random() * 3; // 4–7s slow cycle
        img.style.filter = ONLINE_FILTER;
        img.style.animation = `cb-online-breathe ${dur.toFixed(2)}s ease-in-out infinite`;
        img.style.animationDelay = `-${(Math.random() * dur).toFixed(2)}s`; // random phase so they are out of sync
    } else {
        img.style.animation = '';
        img.style.animationDelay = '';
        img.style.filter = OFFLINE_FILTER;
    }
}

interface Cell {
    el: HTMLDivElement;
    img: HTMLImageElement;
    name: string;
    isOnline: boolean;
}

export default function ConsoleBackground() {
    const { players, online } = useBackgroundPlayers();
    const containerRef = useRef<HTMLDivElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const gridRef = useRef<HTMLDivElement>(null);
    const cellsRef = useRef<Cell[]>([]);
    const onlineRef = useRef(online);
    onlineRef.current = online;

    useEffect(() => {
        const container = containerRef.current;
        const canvas = canvasRef.current;
        const grid = gridRef.current;
        if (!container || !canvas || !grid) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        // Build cells from live player data, or fall back to hardcoded names
        const cells: Cell[] = [];
        const onlineNow = onlineRef.current;

        const buildCell = (name: string, isOnline: boolean) => {
            const cell = document.createElement('div');
            cell.style.cssText = 'position:absolute;transform:translate(-50%,-50%)';
            const img = document.createElement('img');
            img.src = `https://mc-heads.net/body/${name}/80`;
            img.alt = name;
            img.loading = 'lazy';
            img.style.cssText = 'display:block;image-rendering:pixelated;height:5.5vh;width:auto;';
            applyCellState(img, isOnline);
            img.onerror = () => { img.style.display = 'none'; };
            cell.appendChild(img);
            grid.appendChild(cell);
            cells.push({ el: cell, img, name, isOnline });
        };

        if (players.length > 0) {
            // Shuffle once — each player appears exactly once (no duplicates).
            const ordered = [...players].sort(() => Math.random() - 0.5);
            for (const p of ordered) buildCell(p.name, onlineNow.has(p.name.toLowerCase()));
        }
        cellsRef.current = cells;
        // no players → empty grid, particles only

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

        // Online cells already carry their own continuous glow animation (set in
        // applyCellState); offline cells are static and dark. No JS blink loop needed.
        return () => {
            cancelAnimationFrame(animId);
            ro.disconnect();
            cellsRef.current = [];
            while (grid.firstChild) grid.removeChild(grid.firstChild);
        };
    }, [players]); // Rebuild the spiral only when the set of players changes

    // Apply live online/offline status in place — no grid rebuild, so the avatars
    // never flicker or reload. Online players light up + glow; offline go dark.
    useEffect(() => {
        for (const cell of cellsRef.current) {
            const nowOnline = online.has(cell.name.toLowerCase());
            if (nowOnline !== cell.isOnline) {
                cell.isOnline = nowOnline;
                applyCellState(cell.img, nowOnline);
            }
        }
    }, [online]);

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

            {/* Player body spiral grid (above the dark wash so lit players shine) */}
            <div
                ref={gridRef}
                style={{ position: 'absolute', inset: 0, zIndex: 3 }}
            />

            {/* Dark base wash (sits BELOW the avatars) so offline players read as
                "in the dark" and lit online players shine above it. */}
            <div
                style={{
                    position: 'absolute',
                    inset: 0,
                    zIndex: 2,
                    background: 'radial-gradient(ellipse at center, rgba(13,12,8,0.62) 0%, rgba(8,7,4,0.9) 100%)',
                    pointerEvents: 'none',
                }}
            />
        </div>
    );
}
