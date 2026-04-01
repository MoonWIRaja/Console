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
                    border-radius: 1.45rem;
                    border: 1px solid var(--surface-border);
                    background:
                        linear-gradient(165deg, rgba(245, 231, 198, 0.05), rgba(245, 231, 198, 0.015) 42%),
                        var(--surface-elevated);
                    box-shadow:
                        inset 0 1px 0 rgba(245, 231, 198, 0.06),
                        0 22px 40px -30px rgba(0, 0, 0, 0.86),
                        0 0 46px rgba(var(--primary-rgb), 0.08);
                }

                .server-content-shell::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background:
                        radial-gradient(360px 140px at 8% -12%, rgba(var(--primary-rgb), 0.14), transparent 66%),
                        radial-gradient(340px 130px at 90% -12%, rgba(var(--primary-rgb), 0.07), transparent 68%);
                    opacity: 0.5;
                }

                .server-content-body {
                    position: relative;
                    z-index: 1;
                    flex: 1;
                    min-height: 0;
                    overflow: hidden;
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
