import PageContentBlock, { PageContentBlockProps } from '@/components/elements/PageContentBlock';
import React from 'react';
import { ServerContext } from '@/state/server';

interface Props extends PageContentBlockProps {
    title: string;
}

const ServerContentBlock: React.FC<Props> = ({ title, children, ...props }) => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);

    return (
        <PageContentBlock title={`${name} | ${title}`} fullHeight {...props}>
            <style>{`
                .server-content-shell {
                    position: relative;
                    display: flex;
                    height: 100%;
                    min-height: 0;
                    flex-direction: column;
                    overflow: hidden;
                    border-radius: 22px;
                    border: 2px solid #2D4A3E;
                    background-color: #FEF9E1;
                    background-image:
                        repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                        repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px),
                        repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116, 34, 32, 0.04) 4.5px, rgba(116, 34, 32, 0.04) 5px);
                    box-shadow: 4px 4px 0px 0px #2D4A3E;
                }

                .server-content-body {
                    position: relative;
                    z-index: 1;
                    flex: 1;
                    min-height: 0;
                    overflow-y: auto;
                    overflow-x: hidden;
                    padding: 1.05rem;
                }

                @media (max-width: 768px) {
                    .server-content-body {
                        padding: 0.85rem;
                    }
                }
            `}</style>

            <section className='server-content-shell'>
                <div className='server-content-body'>{children}</div>
            </section>
        </PageContentBlock>
    );
};

export default ServerContentBlock;
