<?php

namespace Pterodactyl\Services\Servers\Players\Support;

final class MinecraftNbtReader
{
    private string $data;
    private int $offset = 0;

    private function __construct(string $data)
    {
        $this->data = $data;
    }

    public static function parseRootCompound(string $binary): ?array
    {
        if ($binary === '') {
            return null;
        }

        try {
            $reader = new self($binary);
            $type = $reader->readUnsignedByte();
            if ($type !== 10) {
                return null;
            }

            $reader->readString();
            $payload = $reader->readTagPayload(10);

            return is_array($payload) ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function readTagPayload(int $type): mixed
    {
        return match ($type) {
            0 => null,
            1 => $this->readSignedByte(),
            2 => $this->readSignedShort(),
            3 => $this->readSignedInt(),
            4 => $this->readSignedLong(),
            5 => $this->readFloat(),
            6 => $this->readDouble(),
            7 => $this->readByteArray(),
            8 => $this->readString(),
            9 => $this->readList(),
            10 => $this->readCompound(),
            11 => $this->readIntArray(),
            12 => $this->readLongArray(),
            default => null,
        };
    }

    private function readCompound(): array
    {
        $result = [];

        while (true) {
            $type = $this->readUnsignedByte();
            if ($type === 0) {
                break;
            }

            $name = $this->readString();
            $result[$name] = $this->readTagPayload($type);
        }

        return $result;
    }

    private function readList(): array
    {
        $elementType = $this->readUnsignedByte();
        $length = max(0, $this->readSignedInt());

        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = $this->readTagPayload($elementType);
        }

        return $result;
    }

    private function readByteArray(): array
    {
        $length = max(0, $this->readSignedInt());
        $bytes = $this->readBytes($length);

        $result = [];
        $count = strlen($bytes);
        for ($i = 0; $i < $count; $i++) {
            $value = ord($bytes[$i]);
            $result[] = $value > 127 ? $value - 256 : $value;
        }

        return $result;
    }

    private function readIntArray(): array
    {
        $length = max(0, $this->readSignedInt());
        $result = [];

        for ($i = 0; $i < $length; $i++) {
            $result[] = $this->readSignedInt();
        }

        return $result;
    }

    private function readLongArray(): array
    {
        $length = max(0, $this->readSignedInt());
        $result = [];

        for ($i = 0; $i < $length; $i++) {
            $result[] = $this->readSignedLong();
        }

        return $result;
    }

    private function readString(): string
    {
        $length = $this->readUnsignedShort();
        if ($length === 0) {
            return '';
        }

        return $this->readBytes($length);
    }

    private function readSignedByte(): int
    {
        $value = $this->readUnsignedByte();

        return $value > 127 ? $value - 256 : $value;
    }

    private function readUnsignedByte(): int
    {
        return ord($this->readBytes(1));
    }

    private function readUnsignedShort(): int
    {
        $data = unpack('nvalue', $this->readBytes(2));

        return (int) ($data['value'] ?? 0);
    }

    private function readSignedShort(): int
    {
        $value = $this->readUnsignedShort();

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function readSignedInt(): int
    {
        $data = unpack('Nvalue', $this->readBytes(4));
        $value = (int) ($data['value'] ?? 0);

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private function readSignedLong(): int
    {
        $parts = unpack('Nhi/Nlo', $this->readBytes(8));
        $hi = (int) ($parts['hi'] ?? 0);
        $lo = (int) ($parts['lo'] ?? 0);

        if (($hi & 0x80000000) === 0) {
            return (int) ($hi * 4294967296 + $lo);
        }

        $hi = ~$hi & 0xFFFFFFFF;
        $lo = ~$lo & 0xFFFFFFFF;

        return (int) -(($hi * 4294967296 + $lo) + 1);
    }

    private function readFloat(): float
    {
        $data = unpack('Gvalue', $this->readBytes(4));

        return (float) ($data['value'] ?? 0.0);
    }

    private function readDouble(): float
    {
        $data = unpack('Evalue', $this->readBytes(8));

        return (float) ($data['value'] ?? 0.0);
    }

    private function readBytes(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $chunk = substr($this->data, $this->offset, $length);
        if ($chunk === false || strlen($chunk) !== $length) {
            throw new \RuntimeException('Unexpected end of NBT payload.');
        }

        $this->offset += $length;

        return $chunk;
    }
}

