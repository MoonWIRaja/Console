import { ComponentType } from 'react';
import EulaModalFeature from '@feature/eula/EulaModalFeature';
import JavaVersionModalFeature from '@feature/JavaVersionModalFeature';
import GSLTokenModalFeature from '@feature/GSLTokenModalFeature';
import PIDLimitModalFeature from '@feature/PIDLimitModalFeature';
import SteamDiskSpaceFeature from '@feature/SteamDiskSpaceFeature';
import HytaleOauthRequireFeature from '@feature/HytaleOauthRequireFeature';

const features: Record<string, ComponentType> = {
    eula: EulaModalFeature,
    java_version: JavaVersionModalFeature,
    gsl_token: GSLTokenModalFeature,
    pid_limit: PIDLimitModalFeature,
    steam_disk_space: SteamDiskSpaceFeature,
    hytale_oauth: HytaleOauthRequireFeature,
};

export default features;
