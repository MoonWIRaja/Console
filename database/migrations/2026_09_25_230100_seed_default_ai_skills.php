<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $skills = [
            [
                'slug' => 'pterodactyl-panel',
                'name' => 'Pterodactyl panel',
                'description' => 'How the BurHan panel, Wings and server containers work.',
                'content' => <<<'MD'
- Each game server runs in its own Docker container managed by Wings. The server's files live in the container root "/" (the panel file manager shows the same files).
- Startup command, egg (game type) and variables are set on the Startup page; users change variables there, not in files.
- Console = the game's stdin/stdout. Power: start, stop (graceful), restart, kill (force, can corrupt worlds - only when stop hangs).
- States: offline, starting, running, stopping. "Install" runs the egg install script and can overwrite files.
- Backups: Backups page. Suggest taking a backup before big changes (mod updates, world edits, deleting files).
- Schedules automate commands/power/backups. Subusers get per-permission access.
- Plan limits (CPU %, RAM, disk) are what the customer pays for. Never change them; if a server is out of RAM/disk, explain and suggest optimising or upgrading the plan via Billing.
- Useful files: logs/latest.log (current run), crash-reports/, server.properties, config/, mods/, plugins/.
- If a node is unreachable, tell the user it is a hosting-side problem and to open a support ticket.
MD,
            ],
            [
                'slug' => 'minecraft',
                'name' => 'Minecraft servers',
                'description' => 'Diagnosing and tuning Java Minecraft (Vanilla, Paper, Forge, NeoForge, Fabric).',
                'content' => <<<'MD'
Diagnosing
- Always read logs/latest.log (tail 200-400 lines) first. For crashes read the newest file in crash-reports/.
- "Can't keep up! ... ticks behind" = TPS below 20. Common causes: too many entities/mobs, farms, huge view/simulation distance, generating new chunks, heavy mods, datapack functions running every tick.
- Mod loading failures: look for "Missing or unsupported mandatory dependencies", "Mixin apply failed", or a mod needing a different Minecraft/loader version. Client-only mods (shaders, minimaps, Optifine/Sodium/Iris, sound mods) must not be in a server's mods/ folder.
- Players "Timed out" during CONFIGURATION or LOGIN: usually client mod mismatch or the client's network; check if other players join fine at the same time.
- "Failed to bind to port": the port in server.properties must match the allocation on the panel.
- "OutOfMemoryError": RAM limit reached; reduce mods/view distance or upgrade plan. Do not raise -Xmx above the plan memory.

server.properties quick reference
- view-distance 6-10 and simulation-distance 4-8 are good for performance; online-mode=false means no Mojang auth (cracked); white-list, max-players, motd, difficulty, pvp, spawn-protection.
- Changes to server.properties need a restart.

Tuning (never change gameplay unless asked)
- Paper: config/paper-world-defaults.yml (entity limits, despawn ranges), spigot.yml (entity-activation-range), bukkit.yml (spawn limits).
- Forge/NeoForge/Fabric: performance mods such as ModernFix, FerriteCore, Lithium/Radium, C2ME, ServerCore are server-safe; pregenerate the world with Chunky to stop chunk-generation lag.
- Common commands: list, say <msg>, whitelist add <name>, op <name>, kick <name>, tp, gamerule, save-all, /spark tps, /spark profiler (if spark is installed).
MD,
            ],
            [
                'slug' => 'game-servers',
                'name' => 'Game servers (general)',
                'description' => 'Other common game servers: Rust, Palworld, ARK, Project Zomboid, Terraria, FiveM, Valheim, Source games.',
                'content' => <<<'MD'
- Identify the game from the server's egg/game name before giving advice.
- Project Zomboid: config in Zomboid/Server/<name>.ini and SandboxVars.lua; mods need both Mods= and WorkshopItems=; B42 uses the unstable beta branch.
- Rust: server.cfg in server/<identity>/cfg; oxide/carbon plugins in oxide/plugins.
- Palworld: Pal/Saved/Config/LinuxServer/PalWorldSettings.ini (edit only while stopped, the file is rewritten on shutdown).
- ARK: GameUserSettings.ini and Game.ini under ShooterGame/Saved/Config.
- Valheim: world files in .config/unity3d/IronGate/Valheim; password must be 5+ characters and not contained in the server name.
- Terraria / tModLoader: serverconfig.txt; mods in the Mods folder with enabled.json.
- Source games (CS2, GMod, TF2): server.cfg in <game>/cfg; workshop collections via startup variables.
- Many games rewrite their config on shutdown: stop the server before editing config files, then start it.
- Port problems: the game port must match the panel allocation; extra ports (query, RCON) need extra allocations.
MD,
            ],
        ];

        foreach ($skills as $skill) {
            DB::table('ai_skills')->updateOrInsert(
                ['slug' => $skill['slug']],
                array_merge($skill, ['scope' => 'both', 'enabled' => true, 'created_at' => $now, 'updated_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        DB::table('ai_skills')->whereIn('slug', ['pterodactyl-panel', 'minecraft', 'game-servers'])->delete();
    }
};
