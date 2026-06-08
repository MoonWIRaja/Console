import React from 'react';
import tw from 'twin.macro';

export default () => {
    return (
        <>
            <div
                css={tw`h-full md:w-1/2`}
                style={{ border: '1px solid #2D4A3E', background: '#FEF9E1' }}
            >
                <div css={tw`flex flex-col`}>
                    <h2 css={tw`px-6 py-4 font-bold`} style={{ color: '#742220' }}>Examples</h2>
                    <div css={tw`flex px-6 py-4`} style={{ background: '#F5EFD5', color: '#742220' }}>
                        <div css={tw`w-1/2`}>*/5 * * * *</div>
                        <div css={tw`w-1/2`}>every 5 minutes</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ color: '#742220' }}>
                        <div css={tw`w-1/2`}>0 */1 * * *</div>
                        <div css={tw`w-1/2`}>every hour</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ background: '#F5EFD5', color: '#742220' }}>
                        <div css={tw`w-1/2`}>0 8-12 * * *</div>
                        <div css={tw`w-1/2`}>hour range</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ color: '#742220' }}>
                        <div css={tw`w-1/2`}>0 0 * * *</div>
                        <div css={tw`w-1/2`}>once a day</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ background: '#F5EFD5', color: '#742220' }}>
                        <div css={tw`w-1/2`}>0 0 * * MON</div>
                        <div css={tw`w-1/2`}>every Monday</div>
                    </div>
                </div>
            </div>
            <div
                css={tw`h-full md:w-1/2`}
                style={{ border: '1px solid #2D4A3E', background: '#FEF9E1' }}
            >
                <h2 css={tw`px-6 py-4 font-bold`} style={{ color: '#742220' }}>Special Characters</h2>
                <div css={tw`flex flex-col`}>
                    <div css={tw`flex px-6 py-4`} style={{ background: '#F5EFD5', color: '#742220' }}>
                        <div css={tw`w-1/2`}>*</div>
                        <div css={tw`w-1/2`}>any value</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ color: '#742220' }}>
                        <div css={tw`w-1/2`}>,</div>
                        <div css={tw`w-1/2`}>value list separator</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ background: '#F5EFD5', color: '#742220' }}>
                        <div css={tw`w-1/2`}>-</div>
                        <div css={tw`w-1/2`}>range values</div>
                    </div>
                    <div css={tw`flex px-6 py-4`} style={{ color: '#742220' }}>
                        <div css={tw`w-1/2`}>/</div>
                        <div css={tw`w-1/2`}>step values</div>
                    </div>
                </div>
            </div>
        </>
    );
};
